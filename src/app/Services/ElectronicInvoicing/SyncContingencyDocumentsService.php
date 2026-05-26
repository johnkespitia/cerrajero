<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Services\ElectronicInvoicing\Exceptions\DispatchException;
use App\Services\ElectronicInvoicing\Exceptions\SigningException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Synchronises documents emitted under contingency (`CONTINGENCY_EMITTED`
 * / `CONTINGENCY_PENDING_SYNC`) once DIAN is reachable again.
 *
 * Workflow:
 *  - Pick documents whose `contingency=true` and whose
 *    `contingency_emitted_at` is within the legal window
 *    (`max_window_hours`, default 48 h).
 *  - For each one, transition `CONTINGENCY_EMITTED -> CONTINGENCY_PENDING_SYNC`,
 *    sign through `SigningCoordinator` (if needed) and dispatch through
 *    `DianDispatcher`.
 *  - On success, the dispatcher will produce a terminal DIAN status.
 *    We persist `contingency_synced_at` and append a
 *    `contingency_synced` audit event.
 *  - Documents that exceed `max_window_hours` are moved to
 *    `DEAD_LETTER` with an explicit reason; the operator must
 *    re-emit them as fresh contingency documents.
 */
class SyncContingencyDocumentsService
{
    public function __construct(
        private readonly SigningCoordinator $signingCoordinator,
        private readonly DianDispatcher $dispatcher,
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
        private readonly int $maxWindowHours = 48,
    ) {
    }

    /**
     * @return array{processed:int, synced:int, failed:int, deadLettered:int}
     */
    public function syncPending(int $batchSize = 25): array
    {
        $summary = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'deadLettered' => 0];

        $documents = ElectronicDocument::query()
            ->where('contingency', true)
            ->whereIn('status', [DocumentStatus::CONTINGENCY_EMITTED, DocumentStatus::CONTINGENCY_PENDING_SYNC])
            ->orderBy('contingency_emitted_at', 'asc')
            ->limit($batchSize)
            ->get();

        foreach ($documents as $document) {
            $summary['processed']++;
            $correlationId = (string) Str::uuid();

            if ($this->isOverWindow($document)) {
                $this->markDeadLetter($document, $correlationId);
                $summary['deadLettered']++;
                continue;
            }

            $synced = $this->trySync($document, $correlationId);
            if ($synced) {
                $summary['synced']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function isOverWindow(ElectronicDocument $document): bool
    {
        $emittedAt = $document->contingency_emitted_at;
        if (! $emittedAt instanceof \DateTimeInterface) {
            return false;
        }

        return Carbon::now()->diffInHours($emittedAt) >= $this->maxWindowHours;
    }

    private function trySync(ElectronicDocument $document, string $correlationId): bool
    {
        $scopedLogger = $this->logger
            ->withCorrelationId($correlationId)
            ->withElectronicDocument((int) $document->id);

        try {
            DB::transaction(function () use ($document): void {
                $document->refresh();
                if ($document->status === DocumentStatus::CONTINGENCY_EMITTED) {
                    $document->transitionTo(DocumentStatus::CONTINGENCY_PENDING_SYNC);
                    $document->save();
                }
            });

            // The contingency document is expected to already have
            // ubl_unsigned and (likely) signed XML; we only need to
            // dispatch. The state machine, however, only accepts
            // dispatcher input from XADES_SIGNED, so we re-emit a
            // synthetic status_polled transition to bring the document
            // back into the dispatch pipeline.
            $document->refresh();
            if ($document->status === DocumentStatus::CONTINGENCY_PENDING_SYNC) {
                $this->bringBackToDispatchableState($document);
            }
            $document->refresh();

            if ($document->status === DocumentStatus::UBL_BUILT) {
                $document = $this->signingCoordinator->sign($document, $correlationId);
            }
            if ($document->status === DocumentStatus::XADES_SIGNED) {
                $document = $this->dispatcher->dispatch($document, $correlationId);
            }

            DB::transaction(function () use ($document, $correlationId): void {
                $document->refresh();
                $document->contingency_synced_at = Carbon::now();
                $document->save();
                ElectronicDocumentEvent::create([
                    'electronic_document_id' => $document->id,
                    'event_type' => 'contingency_synced',
                    'payload' => [
                        'final_status' => (string) $document->status,
                    ],
                    'actor' => 'system:contingency_sync',
                    'correlation_id' => $correlationId,
                    'occurred_at' => Carbon::now(),
                ]);
            });

            $this->metrics->increment('electronic_documents_contingency_synced_total', [
                'environment' => (string) $document->environment,
                'final_status' => (string) $document->status,
            ]);
            $scopedLogger->info('document.contingency_synced', [
                'final_status' => (string) $document->status,
            ]);

            return true;
        } catch (DispatchException | SigningException $e) {
            $scopedLogger->warning('document.contingency_sync_failed', [
                'reason' => $e->getMessage(),
                'code' => method_exists($e, 'errorCode') ? $e->errorCode() : null,
            ]);
            $this->appendEvent($document, 'error', [
                'phase' => 'contingency_sync',
                'reason' => $e->getMessage(),
            ], $correlationId);

            return false;
        } catch (Throwable $e) {
            $scopedLogger->warning('document.contingency_sync_unexpected', [
                'reason' => $e->getMessage(),
            ]);
            $this->appendEvent($document, 'error', [
                'phase' => 'contingency_sync',
                'reason' => $e->getMessage(),
            ], $correlationId);

            return false;
        }
    }

    private function bringBackToDispatchableState(ElectronicDocument $document): void
    {
        // CONTINGENCY_PENDING_SYNC -> UBL_BUILT requires the state machine
        // to allow this jump. If the signed XML already exists we can
        // promote directly to XADES_SIGNED via UBL_BUILT.
        DB::transaction(function () use ($document): void {
            $document->refresh();
            $document->transitionTo(DocumentStatus::UBL_BUILT);
            $document->save();
        });
    }

    private function markDeadLetter(ElectronicDocument $document, string $correlationId): void
    {
        try {
            DB::transaction(function () use ($document): void {
                $document->refresh();
                if ($document->status === DocumentStatus::DEAD_LETTER) {
                    return;
                }
                $document->transitionTo(DocumentStatus::DEAD_LETTER);
                $document->save();
            });
            $this->appendEvent($document, 'error', [
                'phase' => 'contingency_sync',
                'reason' => 'window_exceeded',
            ], $correlationId);
            $this->metrics->increment('electronic_documents_contingency_dead_letter_total', [
                'environment' => (string) $document->environment,
            ]);
            $this->logger
                ->withCorrelationId($correlationId)
                ->withElectronicDocument((int) $document->id)
                ->warning('document.contingency_dead_letter', ['reason' => 'window_exceeded']);
        } catch (Throwable $e) {
            // best-effort: nothing else to do, the next run will retry.
        }
    }

    private function appendEvent(ElectronicDocument $document, string $eventType, array $payload, string $correlationId): void
    {
        ElectronicDocumentEvent::create([
            'electronic_document_id' => $document->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'actor' => 'system:contingency_sync',
            'correlation_id' => $correlationId,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
