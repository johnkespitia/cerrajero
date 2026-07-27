<?php

namespace App\Services\ElectronicInvoicing\Contingency;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Coordinates the contingency policy on top of `CircuitBreakerInterface`.
 *
 * Decisions:
 *  - `shouldEmitInContingency()` returns true whenever the breaker is
 *    `open` (so callers stop trying to reach DIAN until recovery).
 *  - `markContingencyDocument()` transitions a freshly created document
 *    (typically in `DRAFT`) into `CONTINGENCY_EMITTED`, persists
 *    contingency metadata and emits a `contingency_emitted` event.
 *  - `recordDispatchSuccess()` / `recordDispatchFailure()` are convenient
 *    bridges so the dispatcher can update breaker state plus emit a
 *    `circuit_breaker_state` gauge in a single call.
 *
 * Side effects are limited to the breaker, the document under treatment
 * and a single audit event. No PII or XML payload is logged.
 */
class ContingencyManager
{
    public function __construct(
        private readonly CircuitBreakerInterface $breaker,
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
    ) {
    }

    public function breaker(): CircuitBreakerInterface
    {
        return $this->breaker;
    }

    public function shouldEmitInContingency(): bool
    {
        return ! $this->breaker->allowsRequest();
    }

    public function recordDispatchSuccess(): void
    {
        $this->breaker->recordSuccess();
        $this->publishGauge();
    }

    public function recordDispatchFailure(): void
    {
        $this->breaker->recordFailure();
        $this->publishGauge();
    }

    /**
     * Marks a document as emitted under contingency.
     *
     * Returns the persisted document with the contingency flag, the new
     * `CONTINGENCY_EMITTED` status and a `contingency_emitted_at`
     * timestamp. The caller is expected to skip the dispatch step.
     */
    public function markContingencyDocument(
        ElectronicDocument $document,
        ?string $reason = null,
        ?string $correlationId = null
    ): ElectronicDocument {
        $reason = $reason ?? 'circuit_breaker_open';
        $correlationId = $correlationId ?: (string) Str::uuid();

        DB::transaction(function () use ($document, $reason, $correlationId): void {
            $document->refresh();
            // The state machine allows DRAFT -> CONTINGENCY_EMITTED. If
            // the caller emitted past DRAFT we still snapshot the
            // contingency metadata but skip the transition; the caller
            // can decide whether to mark the document dead-letter.
            $needsTransition = (string) $document->status === DocumentStatus::DRAFT;
            $document->contingency = true;
            $document->contingency_reason = $reason;
            $document->contingency_emitted_at = Carbon::now();
            if ($needsTransition) {
                $document->transitionTo(DocumentStatus::CONTINGENCY_EMITTED);
            }
            $document->save();

            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => 'contingency_emitted',
                'payload' => [
                    'reason' => $reason,
                    'breaker' => $this->breaker->snapshot(),
                ],
                'actor' => 'system:contingency_manager',
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);
        });

        $this->logger
            ->withCorrelationId($correlationId)
            ->withElectronicDocument((int) $document->id)
            ->warning('document.contingency_emitted', [
                'reason' => $reason,
                'breaker_state' => $this->breaker->state(),
            ]);
        $this->metrics->increment('electronic_documents_contingency_total', [
            'reason' => $reason,
            'environment' => (string) $document->environment,
        ]);
        $this->publishGauge();

        return $document->refresh();
    }

    private function publishGauge(): void
    {
        $stateValue = match ($this->breaker->state()) {
            CircuitBreakerInterface::STATE_CLOSED => 0.0,
            CircuitBreakerInterface::STATE_HALF_OPEN => 0.5,
            CircuitBreakerInterface::STATE_OPEN => 1.0,
            default => 0.0,
        };
        $this->metrics->setGauge('electronic_documents_circuit_breaker_state', $stateValue, [
            'state' => $this->breaker->state(),
        ]);
    }
}
