<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigningUnavailableException;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Services\ElectronicInvoicing\Exceptions\SigningException;
use App\Services\ElectronicInvoicing\Storage\InMemorySignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\SignedXmlStorageInterface;
use App\Services\ElectronicInvoicing\Storage\UnsignedXmlStorageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates the `ubl_built -> xades_signed` transition for an
 * `ElectronicDocument`.
 *
 * The coordinator is the only piece of the domain that touches both the
 * `CertificateProviderInterface` (to load the active `.p12` material) and
 * the `XadesSignerInterface` (to embed the XAdES-EPES signature on the
 * UBL XML). All other slices keep the contracts behind those ports.
 *
 * Side-effects per successful run:
 *  1. Re-hydrates the unsigned XML from `UnsignedXmlStorageInterface`.
 *  2. Loads the active certificate (`P12CertificateProvider` by default).
 *  3. Calls `XadesEpesSigner::signWithMaterial()`.
 *  4. Persists the signed XML through `SignedXmlStorageInterface`.
 *  5. Transitions `UBL_BUILT -> XADES_SIGNED` and persists
 *     `xml_signed_path` on the document.
 *  6. Appends a `xades_signed` event with a sha256 fingerprint of the
 *     signed payload (no XML contents leak through the audit log).
 *
 * On failure the coordinator wraps the underlying exception in a
 * `SigningException`. Callers can decide whether to mark the document
 * as `dead_letter`/`dian_rejected_recoverable`; the coordinator NEVER
 * mutates the document if signing fails.
 *
 * Wiring: this slice ships an in-memory `SignedXmlStorage` by default
 * so the signed payload never leaves the request scope during tests.
 * Production rebinds the interface through the service provider once
 * the encrypted fiscal disk is configured.
 */
class SigningCoordinator
{
    public function __construct(
        private readonly UnsignedXmlStorageInterface $unsignedXmlStorage,
        private readonly SignedXmlStorageInterface $signedXmlStorage,
        private readonly CertificateProviderInterface $certificateProvider,
        private readonly XadesEpesSigner $signer,
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
    ) {
    }

    public function sign(ElectronicDocument $document, ?string $correlationId = null): ElectronicDocument
    {
        if ((string) $document->status !== DocumentStatus::UBL_BUILT) {
            throw SigningException::wrongStatus((string) $document->status);
        }
        $unsignedPath = (string) ($document->xml_unsigned_path ?? '');
        if ($unsignedPath === '') {
            throw SigningException::missingUnsignedXml();
        }
        $unsignedXml = $this->unsignedXmlStorage->retrieve($unsignedPath);
        if ($unsignedXml === null || $unsignedXml === '') {
            throw SigningException::missingUnsignedXml();
        }

        $correlationId = $correlationId ?: (string) Str::uuid();
        $scopedLogger = $this->logger
            ->withCorrelationId($correlationId)
            ->withElectronicDocument((int) $document->id);
        $startedAt = microtime(true);

        try {
            $material = $this->certificateProvider->load(
                (int) $document->company_id,
                (string) $document->environment
            );
        } catch (Throwable $e) {
            throw SigningException::certificateUnavailable($e);
        }

        try {
            $signedXml = $this->signer->signWithMaterial($unsignedXml, $material);
        } catch (XadesEpesSigningUnavailableException $e) {
            throw SigningException::signerUnavailable($e);
        } catch (Throwable $e) {
            throw SigningException::signingFailed($e);
        }

        $signedXml = trim($signedXml);
        if ($signedXml === '') {
            throw SigningException::signingFailed(new \RuntimeException('signer returned an empty payload'));
        }

        return DB::transaction(function () use ($document, $signedXml, $scopedLogger, $correlationId, $startedAt): ElectronicDocument {
            $document->refresh();
            if ((string) $document->status !== DocumentStatus::UBL_BUILT) {
                // Another worker may have advanced the document. Bail out without
                // touching the state again so callers can pick the new status.
                throw SigningException::wrongStatus((string) $document->status);
            }

            $path = $this->signedXmlStorage->store($document, $signedXml);
            $document->xml_signed_path = $path;
            $document->transitionTo(DocumentStatus::XADES_SIGNED);
            $document->save();

            ElectronicDocumentEvent::create([
                'electronic_document_id' => $document->id,
                'event_type' => 'xades_signed',
                'payload' => [
                    'signed_xml_path' => $path,
                    'signed_xml_sha256' => hash('sha256', $signedXml),
                    'signature_algorithm' => $this->signer->signatureAlgorithm(),
                    'canonicalization' => $this->signer->canonicalizationMethod(),
                ],
                'actor' => 'system:signing_coordinator',
                'correlation_id' => $correlationId,
                'occurred_at' => Carbon::now(),
            ]);

            $scopedLogger->info('document.xades_signed', [
                'signed_xml_sha256' => hash('sha256', $signedXml),
            ]);

            $this->metrics->increment('electronic_documents_signed_total', [
                'type' => (string) $document->document_type,
                'environment' => (string) $document->environment,
            ]);
            $this->metrics->observeSeconds(
                'electronic_documents_signing_latency_seconds',
                microtime(true) - $startedAt,
                ['type' => (string) $document->document_type]
            );

            return $document->refresh();
        });
    }

    public static function buildDefault(
        UnsignedXmlStorageInterface $unsignedXmlStorage,
        CertificateProviderInterface $certificateProvider,
        XadesEpesSigner $signer,
        ?SignedXmlStorageInterface $signedXmlStorage = null,
    ): self {
        return new self(
            $unsignedXmlStorage,
            $signedXmlStorage ?? new InMemorySignedXmlStorage(),
            $certificateProvider,
            $signer,
        );
    }
}
