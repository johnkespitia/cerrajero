<?php

namespace App\Services\ElectronicInvoicing\Radian;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\RadianEventCode;
use App\Domain\ElectronicInvoicing\Enums\RadianEventStatus;
use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Models\DianEvent;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper;
use App\Services\ElectronicInvoicing\Dispatch\DianZipPackager;
use App\Services\ElectronicInvoicing\Exceptions\RadianEventException;
use App\Services\ElectronicInvoicing\Storage\DianResponseStorageInterface;
use App\Services\ElectronicInvoicing\Storage\SignedXmlStorageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates emission of RADIAN events (030, 031, 032, 033, 034) for a
 * parent ElectronicDocument.
 *
 * Pipeline per event:
 *  1. Validate the parent document (FEV, DIAN_ACCEPTED, has CUFE).
 *  2. Generate a CUDE-like identifier and build a minimal UBL
 *     ApplicationResponse via `RadianEventBuilder`.
 *  3. Sign the payload with XAdES-EPES using the active certificate.
 *  4. Persist the signed XML and create the `DianEvent` row in
 *     `signed` state, plus a `radian_event_emitted` audit entry on the
 *     parent document.
 *  5. Package via `DianZipPackager` and call
 *     `DianSoapClientInterface::sendEventUpdateStatus()`.
 *  6. Map the response through `DianResponseMapper` (sync semantics) and
 *     transition the `DianEvent` to `dian_accepted` / `dian_rejected`.
 *     Append `radian_event_synced` on success.
 *  7. SOAP failures keep the `DianEvent` in `sent_to_dian` and raise
 *     `RadianEventException::soapFailed()` so the caller can retry.
 *
 * Side effects are encapsulated in DB transactions so partial failures
 * leave the system in a consistent state.
 */
class RadianEventService
{
    public function __construct(
        private readonly CertificateProviderInterface $certificateProvider,
        private readonly XadesEpesSigner $signer,
        private readonly SignedXmlStorageInterface $signedXmlStorage,
        private readonly DianResponseStorageInterface $responseStorage,
        private readonly DianSoapClientInterface $soapClient,
        private readonly RadianEventBuilder $builder = new RadianEventBuilder(),
        private readonly DianZipPackager $packager = new DianZipPackager(),
        private readonly DianResponseMapper $responseMapper = new DianResponseMapper(),
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
    ) {
    }

    public function emit(
        ElectronicDocument $document,
        string $eventCode,
        array $context = [],
        ?string $correlationId = null
    ): DianEvent {
        $this->assertEligible($document, $eventCode);
        $correlationId = $correlationId ?: (string) Str::uuid();
        $actor = (string) ($context['actor'] ?? 'system:radian');

        $cude = $this->generateCude($document, $eventCode);
        $context['cude'] = $cude;
        $context['actor_nit'] = $context['actor_nit'] ?? $document->company?->nit;
        $context['actor_name'] = $context['actor_name'] ?? $document->company?->legal_name;

        try {
            $unsignedXml = $this->builder->build($document, $eventCode, $context);
        } catch (Throwable $e) {
            throw RadianEventException::signingFailed($e);
        }

        try {
            $material = $this->certificateProvider->load((int) $document->company_id, (string) $document->environment);
            $signedXml = $this->signer->signWithMaterial($unsignedXml, $material);
        } catch (Throwable $e) {
            throw RadianEventException::signingFailed($e);
        }

        $event = DB::transaction(function () use ($document, $eventCode, $signedXml, $correlationId, $actor, $cude): DianEvent {
            $event = DianEvent::create([
                'electronic_document_id' => $document->id,
                'event_code' => $eventCode,
                'status' => RadianEventStatus::BUILT,
                'cude' => $cude,
                'actor' => $actor,
                'correlation_id' => $correlationId,
                'emitted_at' => Carbon::now(),
            ]);
            $event->xml_signed_path = $this->signedXmlStorage->store(
                $document,
                $signedXml,
                'radian-' . $eventCode . '-' . $event->id
            );
            $event->status = RadianEventStatus::SIGNED;
            $event->save();

            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => 'radian_event_emitted',
                'payload' => [
                    'event_code' => $eventCode,
                    'dian_event_id' => $event->id,
                ],
                'actor' => $actor,
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);

            return $event;
        });

        $package = $this->packager->package($document, $signedXml);

        DB::transaction(function () use ($event, $package): void {
            $event->refresh();
            $event->status = RadianEventStatus::SENT_TO_DIAN;
            $event->sent_at = Carbon::now();
            $event->save();
        });

        try {
            $response = $this->soapClient->sendEventUpdateStatus($package['file_name'], $package['zip_base64']);
        } catch (Throwable $e) {
            $this->metrics->increment('electronic_documents_radian_errors_total', [
                'event_code' => $eventCode,
                'environment' => (string) $document->environment,
            ]);
            $this->logger
                ->withCorrelationId($correlationId)
                ->withElectronicDocument((int) $document->id)
                ->warning('document.radian_soap_error', [
                    'event_code' => $eventCode,
                    'reason' => $e->getMessage(),
                ]);
            $this->appendDocumentEvent($document, 'error', [
                'phase' => 'radian',
                'event_code' => $eventCode,
                'reason' => $e->getMessage(),
            ], $correlationId, $actor);
            throw RadianEventException::soapFailed($e);
        }

        $outcome = $this->responseMapper->map($response, 'SendBillSync');

        return DB::transaction(function () use ($event, $outcome, $document, $correlationId, $actor, $eventCode): DianEvent {
            $event->refresh();
            if (! empty($outcome['track_id'])) {
                $event->dian_track_id = (string) $outcome['track_id'];
            }
            if (array_key_exists('is_valid', $outcome)) {
                $event->dian_is_valid = $outcome['is_valid'];
            }
            if (! empty($outcome['status_code'])) {
                $event->dian_status_code = (string) $outcome['status_code'];
            }
            if (! empty($outcome['structured_errors'])) {
                $event->dian_error_messages = $outcome['structured_errors'];
            }
            if (! empty($outcome['application_response'])) {
                $xml = base64_decode((string) $outcome['application_response'], true) ?: '';
                if ($xml !== '') {
                    $event->dian_application_response_path = $this->responseStorage->store(
                        $document,
                        $xml,
                        'radian-' . $eventCode . '-' . $event->id . '-application-response'
                    );
                }
            }

            $isAccepted = $outcome['is_valid'] === true;
            $event->status = $isAccepted ? RadianEventStatus::DIAN_ACCEPTED : RadianEventStatus::DIAN_REJECTED;
            $event->resolved_at = Carbon::now();
            $event->save();

            $this->appendDocumentEvent(
                $document,
                $isAccepted ? 'radian_event_synced' : 'error',
                [
                    'phase' => 'radian',
                    'event_code' => $eventCode,
                    'dian_event_id' => $event->id,
                    'status_code' => $outcome['status_code'],
                    'is_valid' => $outcome['is_valid'],
                    'errors' => $outcome['structured_errors'],
                ],
                $correlationId,
                $actor
            );

            $this->metrics->increment('electronic_documents_radian_emitted_total', [
                'event_code' => $eventCode,
                'environment' => (string) $document->environment,
                'outcome' => $isAccepted ? 'accepted' : 'rejected',
            ]);

            return $event;
        });
    }

    public function listForDocument(int $electronicDocumentId): array
    {
        return DianEvent::query()
            ->where('electronic_document_id', $electronicDocumentId)
            ->orderBy('emitted_at')
            ->orderBy('id')
            ->get()
            ->map(fn (DianEvent $e) => [
                'id' => $e->id,
                'event_code' => $e->event_code,
                'status' => $e->status,
                'cude' => $e->cude,
                'dian_track_id' => $e->dian_track_id,
                'dian_status_code' => $e->dian_status_code,
                'dian_is_valid' => $e->dian_is_valid,
                'dian_error_messages' => $e->dian_error_messages,
                'emitted_at' => optional($e->emitted_at)->toIso8601String(),
                'sent_at' => optional($e->sent_at)->toIso8601String(),
                'resolved_at' => optional($e->resolved_at)->toIso8601String(),
            ])
            ->all();
    }

    private function assertEligible(ElectronicDocument $document, string $eventCode): void
    {
        if (! in_array($eventCode, RadianEventCode::ALL, true)) {
            throw RadianEventException::invalidEventCode($eventCode);
        }
        if ((string) $document->document_type !== DocumentType::FEV) {
            throw RadianEventException::unsupportedDocument((string) $document->document_type);
        }
        if ((string) $document->status !== DocumentStatus::DIAN_ACCEPTED) {
            throw RadianEventException::documentNotAccepted((string) $document->status);
        }
        if (trim((string) ($document->cufe_cude ?? '')) === '') {
            throw RadianEventException::missingCufe();
        }
        $duplicate = DianEvent::query()
            ->where('electronic_document_id', $document->id)
            ->where('event_code', $eventCode)
            ->where('status', RadianEventStatus::DIAN_ACCEPTED)
            ->exists();
        if ($duplicate) {
            throw RadianEventException::alreadyAccepted($eventCode);
        }
    }

    private function generateCude(ElectronicDocument $document, string $eventCode): string
    {
        $material = sprintf(
            '%s|%s|%s|%s',
            $document->cufe_cude,
            $eventCode,
            $document->id,
            microtime(true)
        );

        return hash('sha384', $material);
    }

    private function appendDocumentEvent(
        ElectronicDocument $document,
        string $type,
        array $payload,
        string $correlationId,
        string $actor
    ): void {
        ElectronicDocumentEvent::create([
            'electronic_document_id' => $document->id,
            'event_type' => $type,
            'payload' => $payload,
            'actor' => $actor,
            'correlation_id' => $correlationId,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
