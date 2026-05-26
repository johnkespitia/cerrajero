<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\CufeCalculatorInterface;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator;
use App\Infrastructure\ElectronicInvoicing\Cufe\SoftwareSecurityCodeCalculator;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Services\ElectronicInvoicing\DianDispatcher;
use App\Services\ElectronicInvoicing\Exceptions\IncompleteEmissionPayloadException;
use App\Services\ElectronicInvoicing\SigningCoordinator;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\UnsignedXmlStorageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates the local emission of an ElectronicDocument up to UBL_BUILT.
 *
 * This service intentionally stops short of any DIAN interaction:
 *  - It creates an ElectronicDocument row in DRAFT.
 *  - It calculates CUFE/CUDE and (optionally) software_security_code.
 *  - It runs the UBL builder for the resolved document type.
 *  - It persists the unsigned XML through UnsignedXmlStorageInterface.
 *  - It transitions DRAFT -> UBL_BUILT through StateTransitions.
 *  - It appends queued, cufe_calculated and ubl_built events to the
 *    append-only ElectronicDocumentEvent log.
 *
 * The actual XAdES-EPES signing and SOAP dispatch live in later slices.
 * Until then, callers can request a "dry run" by simply consuming the
 * UBL_BUILT document (no network is ever touched here).
 */
final class DocumentEmitter
{
    /** @var DocumentAssembler */
    private $assembler;

    /** @var CufeCalculatorInterface */
    private $cufeCalculator;

    /** @var SoftwareSecurityCodeCalculator */
    private $softwareSecurityCalculator;

    /** @var UblBuilderRegistry */
    private $builderRegistry;

    /** @var UnsignedXmlStorageInterface */
    private $xmlStorage;

    /** @var MetricsRecorderInterface */
    private $metrics;

    /** @var ElectronicInvoicingLoggerInterface */
    private $logger;

    /** @var SigningCoordinator|null */
    private $signingCoordinator;

    /** @var bool */
    private $signingEnabled;

    /** @var DianDispatcher|null */
    private $dispatcher;

    /** @var bool */
    private $dispatchEnabled;

    /** @var string */
    private $dispatchMode;

    public function __construct(
        DocumentAssembler $assembler,
        ?CufeCalculatorInterface $cufeCalculator = null,
        ?SoftwareSecurityCodeCalculator $softwareSecurityCalculator = null,
        ?UblBuilderRegistry $builderRegistry = null,
        ?UnsignedXmlStorageInterface $xmlStorage = null,
        ?MetricsRecorderInterface $metrics = null,
        ?ElectronicInvoicingLoggerInterface $logger = null,
        ?SigningCoordinator $signingCoordinator = null,
        ?bool $signingEnabled = null,
        ?DianDispatcher $dispatcher = null,
        ?bool $dispatchEnabled = null,
        ?string $dispatchMode = null
    ) {
        $this->assembler = $assembler;
        $this->cufeCalculator = $cufeCalculator ?: new Sha384CufeCalculator();
        $this->softwareSecurityCalculator = $softwareSecurityCalculator ?: new SoftwareSecurityCodeCalculator();
        $this->builderRegistry = $builderRegistry ?: UblBuilderRegistry::default();
        $this->xmlStorage = $xmlStorage ?: new InMemoryUnsignedXmlStorage();
        $this->metrics = $metrics ?: new InMemoryMetricsRecorder();
        $this->logger = $logger ?: new ElectronicInvoicingLogger();
        $this->signingCoordinator = $signingCoordinator;
        $this->signingEnabled = $signingEnabled
            ?? (function_exists('config') ? (bool) config('electronic-invoicing.signing.enabled', false) : false);
        $this->dispatcher = $dispatcher;
        $this->dispatchEnabled = $dispatchEnabled
            ?? (function_exists('config') ? (bool) config('electronic-invoicing.dispatch.enabled', false) : false);
        $this->dispatchMode = $dispatchMode
            ?? (function_exists('config') ? (string) config('electronic-invoicing.dispatch.mode', 'sync') : 'sync');
    }

    /**
     * Emit a brand new ElectronicDocument.
     *
     * @param array $context Emission context (see DocumentAssembler::assemble()).
     *     Additional keys consumed here:
     *      - source_meta: ['source_type' => string, 'source_id' => int|string]
     *      - acquirer_id: ?int           (when an ElectronicDocumentAcquirer was persisted)
     *      - references_document_id: ?int
     *      - software_credential: ?[
     *            'software_id' => string,
     *            'pin' => string,            // resolved via SecretManagerInterface upstream
     *        ]
     *      - notes: ?string
     */
    public function emit(array $context): ElectronicDocument
    {
        $payload = $this->assembler->assemble($context);
        $documentType = (string) $context['document_type'];
        $environment = (string) $context['environment'];
        DocumentType::assert($documentType);
        FiscalEnvironment::assert($environment);

        $sourceMeta = isset($context['source_meta']) && is_array($context['source_meta'])
            ? $context['source_meta']
            : [];
        if (empty($sourceMeta['source_type']) || !array_key_exists('source_id', $sourceMeta)) {
            throw IncompleteEmissionPayloadException::for('source_meta');
        }

        $correlationId = (string) ($context['correlation_id'] ?? Str::uuid());
        $scopedLogger = $this->logger->withCorrelationId($correlationId);
        $emissionStartedAt = microtime(true);

        return DB::transaction(function () use ($context, $payload, $documentType, $environment, $sourceMeta, $scopedLogger, $correlationId, $emissionStartedAt) {
            $document = $this->createDraft($context, $payload, $documentType, $environment, $sourceMeta);
            $scopedLogger = $scopedLogger->withElectronicDocument((int) $document->id);
            $scopedLogger->info('document.queued', [
                'document_type' => $documentType,
                'environment' => $environment,
                'source_type' => $sourceMeta['source_type'] ?? null,
            ]);
            $this->appendEvent($document, 'queued', [
                'document_type' => $documentType,
                'environment' => $environment,
                'source' => $sourceMeta,
            ], $correlationId);

            $softwareSecurityCode = $this->resolveSoftwareSecurityCode(
                $context['software_credential'] ?? null,
                $payload['document']['number']
            );
            if ($softwareSecurityCode !== null) {
                $document->software_security_code = $softwareSecurityCode;
                $payload['dian_extensions']['software_security_code'] = $softwareSecurityCode;
            }
            if (!empty($context['software_credential']['software_id'])) {
                $payload['dian_extensions']['software_id'] = (string) $context['software_credential']['software_id'];
                $payload['dian_extensions']['provider_nit'] = $payload['supplier']['nit'] ?? null;
            }

            $cufe = $this->cufeCalculator->calculate($documentType, $payload['cufe_fields']);
            $document->cufe_cude = $cufe->value();
            $payload['document']['cufe'] = $cufe->value();

            $this->appendEvent($document, 'cufe_calculated', [
                'algorithm' => 'sha384',
                'document_type' => $documentType,
                'document_number' => $payload['document']['number'],
            ], $correlationId);

            $builder = $this->builderRegistry->resolve($documentType);
            $xml = $builder->build($payload);

            $xmlPath = $this->xmlStorage->store($document, $xml);
            $document->xml_unsigned_path = $xmlPath;

            $document->transitionTo(DocumentStatus::UBL_BUILT);
            $document->save();

            $this->appendEvent($document, 'ubl_built', [
                'builder' => get_class($builder),
                'unsigned_xml_path' => $xmlPath,
                'cufe' => $cufe->value(),
            ], $correlationId);

            $scopedLogger->info('document.ubl_built', [
                'document_type' => $documentType,
                'environment' => $environment,
                'builder' => get_class($builder),
            ]);

            $this->metrics->increment(
                'electronic_documents_emitted_total',
                ['type' => $documentType, 'status' => DocumentStatus::UBL_BUILT, 'environment' => $environment]
            );
            $this->metrics->observeSeconds(
                'electronic_documents_emission_latency_seconds',
                microtime(true) - $emissionStartedAt,
                ['type' => $documentType, 'environment' => $environment]
            );

            if ($this->signingEnabled && $this->signingCoordinator !== null) {
                $document = $this->signingCoordinator->sign($document, $correlationId);
            }
            if ($this->dispatchEnabled && $this->dispatcher !== null
                && (string) $document->status === DocumentStatus::XADES_SIGNED) {
                $document = $this->dispatcher->dispatch($document, $correlationId, $this->dispatchMode);
            }

            $document->load('events');
            return $document;
        });
    }

    private function createDraft(
        array $context,
        array $payload,
        string $documentType,
        string $environment,
        array $sourceMeta
    ): ElectronicDocument {
        $issuedAt = $context['issued_at'];
        $totals = $payload['totals'];

        $document = new ElectronicDocument();
        $document->company_id = (int) $context['company']->id;
        $document->resolution_id = isset($context['numbering']['resolution_id'])
            ? (int) $context['numbering']['resolution_id']
            : null;
        $document->document_type = $documentType;
        $document->reference_code = (string) Str::uuid();
        $document->dian_number = (string) $payload['document']['number'];
        $document->qr_url = $context['qr_url'] ?? null;
        $document->status = DocumentStatus::DRAFT;
        $document->environment = $environment;
        $document->subtotal = $this->money($totals['line_extension_amount']);
        $document->total_taxes = $this->money(
            (string) (((float) $totals['tax_inclusive_amount']) - ((float) $totals['tax_exclusive_amount']))
        );
        $document->total = $this->money($totals['payable_amount']);
        $document->currency_code = (string) $payload['document']['currency'];
        $document->issue_date = Carbon::instance($issuedAt);
        $document->payment_means_code = $payload['payment']['means_code'] ?? null;
        $document->payment_terms = $payload['payment']['terms_code'] ?? null;
        $document->due_date = isset($payload['payment']['payment_due_date'])
            ? Carbon::parse($payload['payment']['payment_due_date'])
            : null;
        $document->notes = $context['notes'] ?? null;
        $document->source_type = $sourceMeta['source_type'] ?? null;
        $document->source_id = isset($sourceMeta['source_id']) ? (int) $sourceMeta['source_id'] : null;
        $document->acquirer_id = isset($context['acquirer_id']) ? (int) $context['acquirer_id'] : null;
        $document->references_document_id = isset($context['references_document_id'])
            ? (int) $context['references_document_id']
            : null;
        $document->contingency = false;
        $document->attempts = 0;
        $document->save();

        return $document;
    }

    private function resolveSoftwareSecurityCode($credential, string $documentNumber): ?string
    {
        if (!is_array($credential)) {
            return null;
        }
        if (empty($credential['software_id']) || empty($credential['pin'])) {
            return null;
        }
        return $this->softwareSecurityCalculator->calculate(
            (string) $credential['software_id'],
            $documentNumber,
            (string) $credential['pin']
        );
    }

    private function appendEvent(
        ElectronicDocument $document,
        string $eventType,
        array $payload,
        ?string $correlationId = null
    ): void {
        ElectronicDocumentEvent::create([
            'electronic_document_id' => $document->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'actor' => 'system:document_emitter',
            'correlation_id' => $correlationId
                ?? (string) ($document->reference_code ?? Str::uuid()),
            'occurred_at' => Carbon::now(),
        ]);
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Whitelist guard used by external callers to confirm we currently support
     * a given (document_type, environment) pair without instantiating the
     * full builder graph. Useful for higher-level controllers.
     */
    public function canEmit(string $documentType, string $environment): bool
    {
        if (!DocumentType::isValid($documentType) || !FiscalEnvironment::isValid($environment)) {
            return false;
        }
        return in_array($documentType, $this->builderRegistry->registeredTypes(), true);
    }
}
