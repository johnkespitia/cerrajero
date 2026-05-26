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
use App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper;
use App\Services\ElectronicInvoicing\Dispatch\DianZipPackager;
use App\Services\ElectronicInvoicing\Exceptions\DispatchException;
use App\Services\ElectronicInvoicing\Storage\DianResponseStorageInterface;
use App\Services\ElectronicInvoicing\Storage\SignedXmlStorageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates the `xades_signed -> sent_to_dian -> dian_*` flow.
 *
 * The dispatcher is the only piece of the domain that crosses the
 * `DianSoapClientInterface` boundary. It runs the following pipeline:
 *
 *  1. Re-hydrates the XAdES-signed XML from `SignedXmlStorageInterface`.
 *  2. Packages it through `DianZipPackager` (file name + ZIP + b64).
 *  3. Transitions `XADES_SIGNED -> SENT_TO_DIAN` and persists
 *     `dian_zip_key` (sha256 of the ZIP bytes for traceability).
 *  4. Calls `DianSoapClientInterface::sendBillSync()` (or async, when
 *     `mode = 'async'`). All retries / circuit breaker rules live in
 *     `DocumentReconciler` (slice C).
 *  5. Persists `dian_track_id`, ApplicationResponse XML
 *     (`dian_application_response_path`), `dian_status_code`,
 *     `dian_is_valid` and `dian_error_messages`.
 *  6. Transitions the document to the terminal status returned by
 *     `DianResponseMapper`.
 *  7. Appends `sent_sync` / `sent_async` and `dian_accepted` /
 *     `dian_rejected` events. None of them carries XML or PII.
 *
 * Failure modes:
 *  - SOAP transport raises -> wrap in `DispatchException::soapFailed()`
 *    so the caller can decide whether to roll back the SENT_TO_DIAN
 *    transition (slice B leaves the state stuck and emits an `error`
 *    event; reconciliator/contingency owns the recovery).
 *  - Packaging fails -> `DispatchException::packagingFailed()`; the
 *    document stays at XADES_SIGNED.
 */
class DianDispatcher
{
    public function __construct(
        private readonly SignedXmlStorageInterface $signedXmlStorage,
        private readonly DianResponseStorageInterface $responseStorage,
        private readonly DianSoapClientInterface $soapClient,
        private readonly DianZipPackager $packager = new DianZipPackager(),
        private readonly DianResponseMapper $responseMapper = new DianResponseMapper(),
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
        private readonly string $defaultMode = 'sync',
    ) {
    }

    public function dispatch(ElectronicDocument $document, ?string $correlationId = null, ?string $mode = null): ElectronicDocument
    {
        if ((string) $document->status !== DocumentStatus::XADES_SIGNED) {
            throw DispatchException::wrongStatus((string) $document->status);
        }
        $signedPath = (string) ($document->xml_signed_path ?? '');
        if ($signedPath === '') {
            throw DispatchException::missingSignedXml();
        }
        $signedXml = $this->signedXmlStorage->retrieve($signedPath);
        if ($signedXml === null || $signedXml === '') {
            throw DispatchException::missingSignedXml();
        }

        $mode = $mode ?: $this->defaultMode;
        $correlationId = $correlationId ?: (string) Str::uuid();
        $scopedLogger = $this->logger
            ->withCorrelationId($correlationId)
            ->withElectronicDocument((int) $document->id);
        $startedAt = microtime(true);

        try {
            $package = $this->packager->package($document, $signedXml);
        } catch (Throwable $e) {
            throw DispatchException::packagingFailed($e);
        }

        DB::transaction(function () use ($document, $package, $correlationId): void {
            $document->refresh();
            if ((string) $document->status !== DocumentStatus::XADES_SIGNED) {
                throw DispatchException::wrongStatus((string) $document->status);
            }
            $document->dian_zip_key = (string) $package['zip_sha256'];
            $document->last_attempt_at = Carbon::now();
            $document->attempts = (int) $document->attempts + 1;
            $document->transitionTo(DocumentStatus::SENT_TO_DIAN);
            $document->save();

            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => 'sent_sync',
                'payload' => [
                    'file_name' => $package['file_name'],
                    'zip_sha256' => $package['zip_sha256'],
                ],
                'actor' => 'system:dian_dispatcher',
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);
        });

        $operation = $mode === 'async' ? 'SendBillAsync' : 'SendBillSync';
        try {
            $response = $operation === 'SendBillAsync'
                ? $this->soapClient->sendBillAsync($package['file_name'], $package['zip_base64'])
                : $this->soapClient->sendBillSync($package['file_name'], $package['zip_base64']);
        } catch (Throwable $e) {
            $this->metrics->increment('electronic_documents_soap_errors_total', [
                'type' => (string) $document->document_type,
                'operation' => $operation,
                'environment' => (string) $document->environment,
            ]);
            $scopedLogger->warning('document.dian_soap_error', [
                'operation' => $operation,
                'reason' => $e->getMessage(),
            ]);
            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => 'error',
                'payload' => [
                    'phase' => 'dispatch',
                    'operation' => $operation,
                    'reason' => $e->getMessage(),
                ],
                'actor' => 'system:dian_dispatcher',
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);
            throw DispatchException::soapFailed($e);
        }

        $outcome = $this->responseMapper->map($response, $operation);

        return DB::transaction(function () use ($document, $outcome, $correlationId, $operation, $scopedLogger, $startedAt): ElectronicDocument {
            $document->refresh();

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

            $targetStatus = (string) $outcome['target_status'];
            $document->transitionTo($targetStatus);
            $document->save();

            $eventType = $this->resolveEventType($targetStatus, $operation);
            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => $eventType,
                'payload' => [
                    'operation' => $operation,
                    'status_code' => $outcome['status_code'],
                    'is_valid' => $outcome['is_valid'],
                    'track_id' => $outcome['track_id'],
                    'errors' => $outcome['structured_errors'],
                ],
                'actor' => 'system:dian_dispatcher',
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);

            $scopedLogger->info('document.dian_response', [
                'operation' => $operation,
                'target_status' => $targetStatus,
                'status_code' => $outcome['status_code'],
                'is_valid' => $outcome['is_valid'],
            ]);

            $this->metrics->increment('electronic_documents_dispatched_total', [
                'type' => (string) $document->document_type,
                'operation' => $operation,
                'status' => $targetStatus,
                'environment' => (string) $document->environment,
            ]);
            $this->metrics->observeSeconds(
                'electronic_documents_dispatch_latency_seconds',
                microtime(true) - $startedAt,
                ['type' => (string) $document->document_type, 'operation' => $operation]
            );

            return $document->refresh();
        });
    }

    private function resolveEventType(string $targetStatus, string $operation): string
    {
        if ($targetStatus === DocumentStatus::DIAN_ACCEPTED) {
            return 'dian_accepted';
        }
        if (in_array($targetStatus, [DocumentStatus::DIAN_REJECTED_RECOVERABLE, DocumentStatus::DIAN_REJECTED_TERMINAL], true)) {
            return 'dian_rejected';
        }
        if ($targetStatus === DocumentStatus::DIAN_TRACK_RECEIVED) {
            return 'sent_async';
        }

        return $operation === 'SendBillAsync' ? 'sent_async' : 'sent_sync';
    }
}
