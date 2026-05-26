<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Services\ElectronicInvoicing\Contingency\ContingencyManager;
use App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper;
use App\Services\ElectronicInvoicing\Storage\DianResponseStorageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reconciles documents that are stuck mid-flight in the DIAN flow.
 *
 * Recovers two families of documents:
 *
 *  1. `SENT_TO_DIAN`            -> the dispatch happened but no terminal
 *                                  response was persisted. We attempt
 *                                  `GetStatusZip(trackId)` when a track
 *                                  is available; otherwise we mark the
 *                                  document as `DIAN_VALIDATING` and let
 *                                  the next round retry. After
 *                                  `stuck_after_minutes` without
 *                                  resolution we fall through to
 *                                  `DEAD_LETTER`.
 *  2. `DIAN_TRACK_RECEIVED` /   -> classic async polling. `GetStatusZip`
 *     `DIAN_VALIDATING`           returns the final outcome and we map
 *                                  it through `DianResponseMapper`.
 *
 * The reconciler is idempotent: every iteration only walks documents
 * whose `last_attempt_at` is older than `interval_minutes` (or null),
 * never mutates documents that already transitioned to a terminal
 * status, and persists the new attempts atomically.
 */
class DocumentReconciler
{
    public function __construct(
        private readonly DianSoapClientInterface $soapClient,
        private readonly DianResponseStorageInterface $responseStorage,
        private readonly DianResponseMapper $mapper = new DianResponseMapper(),
        private readonly ContingencyManager $contingencyManager = new ContingencyManager(
            new \App\Services\ElectronicInvoicing\Contingency\InMemoryCircuitBreaker(),
        ),
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
        private readonly int $intervalMinutes = 5,
        private readonly int $stuckAfterMinutes = 10,
    ) {
    }

    /**
     * Walks the pending document inbox and attempts a single reconciliation
     * step per document. Returns a summary report.
     *
     * @return array{processed:int, accepted:int, rejected:int, stuck:int, errors:int, deadLettered:int}
     */
    public function reconcilePending(int $batchSize = 50): array
    {
        $summary = [
            'processed' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'stuck' => 0,
            'errors' => 0,
            'deadLettered' => 0,
        ];

        $cutoff = Carbon::now()->subMinutes($this->intervalMinutes);
        $documents = ElectronicDocument::query()
            ->whereIn('status', [
                DocumentStatus::SENT_TO_DIAN,
                DocumentStatus::DIAN_TRACK_RECEIVED,
                DocumentStatus::DIAN_VALIDATING,
            ])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<=', $cutoff);
            })
            ->orderBy('last_attempt_at', 'asc')
            ->limit($batchSize)
            ->get();

        foreach ($documents as $document) {
            $summary['processed']++;
            try {
                $outcome = $this->reconcileOne($document);
            } catch (Throwable $e) {
                $summary['errors']++;
                $this->contingencyManager->recordDispatchFailure();
                $this->logger
                    ->withCorrelationId((string) Str::uuid())
                    ->withElectronicDocument((int) $document->id)
                    ->warning('document.reconcile_error', ['reason' => $e->getMessage()]);
                continue;
            }
            switch ($outcome) {
                case 'accepted':
                    $summary['accepted']++;
                    break;
                case 'rejected':
                    $summary['rejected']++;
                    break;
                case 'stuck':
                    $summary['stuck']++;
                    break;
                case 'dead_lettered':
                    $summary['deadLettered']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * Reconcile a single document. Returns:
     *  - `accepted`        when the document reached `DIAN_ACCEPTED`.
     *  - `rejected`        when the document reached a terminal rejected state.
     *  - `pending`         when DIAN still hasn't returned a verdict.
     *  - `stuck`           when the document exceeded `stuck_after_minutes`.
     *  - `dead_lettered`   when the document was moved to DEAD_LETTER.
     */
    public function reconcileOne(ElectronicDocument $document): string
    {
        $correlationId = (string) Str::uuid();
        $scopedLogger = $this->logger
            ->withCorrelationId($correlationId)
            ->withElectronicDocument((int) $document->id);

        if ($this->isStuckOverWindow($document)) {
            return $this->markDeadLetter($document, $correlationId, 'stuck_over_window')
                ? 'dead_lettered'
                : 'stuck';
        }

        $trackId = (string) ($document->dian_track_id ?? '');
        if ($trackId === '') {
            // SENT_TO_DIAN without trackId: assume the dispatcher
            // request hit the network but DIAN never echoed the id; the
            // safest move is to mark it as DIAN_VALIDATING and let the
            // operator retry manually via the dashboard.
            $this->transitionWithEvent(
                $document,
                DocumentStatus::DIAN_VALIDATING,
                'status_polled',
                ['reason' => 'no_track_id'],
                $correlationId
            );
            $this->contingencyManager->recordDispatchFailure();
            return 'stuck';
        }

        try {
            $response = $this->soapClient->getStatusZip($trackId);
        } catch (Throwable $e) {
            $this->contingencyManager->recordDispatchFailure();
            $this->metrics->increment('electronic_documents_reconcile_errors_total', [
                'environment' => (string) $document->environment,
            ]);
            $this->appendEvent($document, 'error', [
                'phase' => 'reconcile',
                'reason' => $e->getMessage(),
            ], $correlationId);
            throw $e;
        }

        // GetStatusZip returns the same shape as SendBillSync (IsValid +
        // StatusCode + ErrorMessage + optional XmlBytes). When DIAN does
        // not echo IsValid yet, the mapper falls back to DIAN_VALIDATING
        // which the reconciler treats as "still pending".
        $outcome = $this->mapper->map($response, 'SendBillSync');

        if ($outcome['target_status'] === DocumentStatus::DIAN_VALIDATING) {
            // Keep the document under validation; do not transition to a
            // terminal state until DIAN echoes IsValid.
            $this->markPending($document, $correlationId, $outcome);
            $this->contingencyManager->recordDispatchSuccess();
            return 'pending';
        }

        $persisted = $this->persistOutcome($document, $outcome, $correlationId);
        $this->contingencyManager->recordDispatchSuccess();

        $scopedLogger->info('document.reconciled', [
            'target_status' => $persisted->status,
            'status_code' => $persisted->dian_status_code,
        ]);

        if ($persisted->status === DocumentStatus::DIAN_ACCEPTED) {
            return 'accepted';
        }
        if (in_array($persisted->status, [DocumentStatus::DIAN_REJECTED_TERMINAL, DocumentStatus::DIAN_REJECTED_RECOVERABLE], true)) {
            return 'rejected';
        }

        return 'pending';
    }

    private function isStuckOverWindow(ElectronicDocument $document): bool
    {
        $sent = $document->last_attempt_at;
        if (! $sent instanceof \DateTimeInterface) {
            return false;
        }

        return Carbon::now()->diffInMinutes($sent) >= ($this->stuckAfterMinutes * 6);
    }

    private function markDeadLetter(ElectronicDocument $document, string $correlationId, string $reason): bool
    {
        try {
            DB::transaction(function () use ($document, $correlationId, $reason): void {
                $document->refresh();
                if ($document->status === DocumentStatus::DEAD_LETTER) {
                    return;
                }
                $document->transitionTo(DocumentStatus::DEAD_LETTER);
                $document->save();
                ElectronicDocumentEvent::create([
                    'electronic_document_id' => $document->id,
                    'event_type' => 'error',
                    'payload' => [
                        'phase' => 'reconcile',
                        'reason' => $reason,
                    ],
                    'actor' => 'system:reconciler',
                    'correlation_id' => $correlationId,
                    'occurred_at' => Carbon::now(),
                ]);
            });
            $this->metrics->increment('electronic_documents_dead_letter_total', [
                'reason' => $reason,
                'environment' => (string) $document->environment,
            ]);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function markPending(ElectronicDocument $document, string $correlationId, array $outcome): void
    {
        DB::transaction(function () use ($document, $correlationId, $outcome): void {
            $document->refresh();
            if ($document->status !== DocumentStatus::DIAN_VALIDATING) {
                $document->transitionTo(DocumentStatus::DIAN_VALIDATING);
            }
            $document->last_attempt_at = Carbon::now();
            $document->save();
            $this->appendEvent($document, 'status_polled', [
                'phase' => 'reconcile',
                'reason' => 'dian_still_processing',
                'status_code' => $outcome['status_code'],
            ], $correlationId);
        });
    }

    private function persistOutcome(ElectronicDocument $document, array $outcome, string $correlationId): ElectronicDocument
    {
        return DB::transaction(function () use ($document, $outcome, $correlationId): ElectronicDocument {
            $document->refresh();
            $targetStatus = (string) $outcome['target_status'];

            if (! empty($outcome['track_id'])) {
                $document->dian_track_id = (string) $outcome['track_id'];
            }
            if (array_key_exists('is_valid', $outcome)) {
                $document->dian_is_valid = $outcome['is_valid'];
            }
            if (! empty($outcome['status_code'])) {
                $document->dian_status_code = (string) $outcome['status_code'];
            }
            if (! empty($outcome['structured_errors'])) {
                $document->dian_error_messages = $outcome['structured_errors'];
            }
            if (! empty($outcome['application_response'])) {
                $xml = base64_decode((string) $outcome['application_response'], true) ?: '';
                if ($xml !== '') {
                    $document->dian_application_response_path = $this->responseStorage->store(
                        $document,
                        $xml,
                        'application-response'
                    );
                }
            }
            $document->last_attempt_at = Carbon::now();

            if ($document->status !== $targetStatus) {
                $document->transitionTo($targetStatus);
            }
            $document->save();

            $eventType = match ($targetStatus) {
                DocumentStatus::DIAN_ACCEPTED => 'dian_accepted',
                DocumentStatus::DIAN_REJECTED_RECOVERABLE,
                DocumentStatus::DIAN_REJECTED_TERMINAL => 'dian_rejected',
                default => 'status_polled',
            };
            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => $eventType,
                'payload' => [
                    'phase' => 'reconcile',
                    'status_code' => $outcome['status_code'],
                    'is_valid' => $outcome['is_valid'],
                    'track_id' => $outcome['track_id'],
                    'errors' => $outcome['structured_errors'],
                ],
                'actor' => 'system:reconciler',
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);

            return $document->refresh();
        });
    }

    private function transitionWithEvent(
        ElectronicDocument $document,
        string $targetStatus,
        string $eventType,
        array $payload,
        string $correlationId
    ): void {
        DB::transaction(function () use ($document, $targetStatus, $eventType, $payload, $correlationId): void {
            $document->refresh();
            if ($document->status === $targetStatus) {
                return;
            }
            $document->transitionTo($targetStatus);
            $document->last_attempt_at = Carbon::now();
            $document->save();
            $this->appendEvent($document, $eventType, $payload, $correlationId);
        });
    }

    private function appendEvent(ElectronicDocument $document, string $eventType, array $payload, string $correlationId): void
    {
        ElectronicDocumentEvent::create([
            'electronic_document_id' => $document->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'actor' => 'system:reconciler',
            'correlation_id' => $correlationId,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
