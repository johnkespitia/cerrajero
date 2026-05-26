<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use App\Infrastructure\ElectronicInvoicing\Secrets\ArraySecretManager;
use App\Infrastructure\ElectronicInvoicing\Secrets\SecretUnavailableException;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\DianSoftwareCredential;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\KioskInvoice;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionException;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionInvalidPayloadException;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionUnavailableException;
use App\Services\ElectronicInvoicing\Exceptions\NumberingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates the local emission of an ElectronicDocument starting from a
 * KioskInvoice. The service is intentionally network-free in this slice:
 * it resolves fiscal context, validates the acquirer payload, allocates a
 * dian_number, and delegates to DocumentEmitter to reach `ubl_built`.
 *
 * Errors are normalised into KioskEmissionException subclasses so the
 * controller can decide whether to respond 422 (invalid payload) or attach a
 * structured `electronic_document_error` (configuration gaps).
 */
final class KioskInvoiceEmissionService
{
    /** @var DocumentResolver */
    private $resolver;

    /** @var NumberingAllocator */
    private $allocator;

    /** @var KioskEmissionContextBuilder */
    private $contextBuilder;

    /** @var DocumentEmitter */
    private $emitter;

    /** @var SecretManagerInterface */
    private $secrets;

    public function __construct(
        ?DocumentResolver $resolver = null,
        ?NumberingAllocator $allocator = null,
        ?KioskEmissionContextBuilder $contextBuilder = null,
        ?DocumentEmitter $emitter = null,
        ?SecretManagerInterface $secrets = null
    ) {
        $this->resolver = $resolver ?: new DocumentResolver();
        $this->allocator = $allocator ?: new NumberingAllocator();
        $this->contextBuilder = $contextBuilder ?: new KioskEmissionContextBuilder();
        $this->emitter = $emitter ?: new DocumentEmitter(new DocumentAssembler());
        $this->secrets = $secrets ?: new ArraySecretManager();
    }

    /**
     * Emit an ElectronicDocument (state -> ubl_built) for the given KioskInvoice.
     *
     * @param KioskInvoice $invoice          Already persisted invoice with details loaded.
     * @param array|null   $acquirerPayload  Raw `acquirer` block from the request, when FEV.
     */
    public function emitForKioskInvoice(KioskInvoice $invoice, ?array $acquirerPayload = null): ElectronicDocument
    {
        $this->assertEnabled();

        $invoice->loadMissing(['details.kiosk_unit.product.tax', 'payment_type']);

        $environment = $this->resolveEnvironment();
        $company = $this->resolveCompany($environment);

        $requiresAcquirer = !empty($invoice->electronic_invoice);
        $acquirer = $this->resolveAcquirer($invoice, $acquirerPayload, $requiresAcquirer);
        if ($acquirer !== null && empty($invoice->acquirer_id)) {
            $invoice->acquirer_id = $acquirer->id;
        }

        $documentType = $this->resolver->forKioskInvoice($invoice);

        $resolutionPreview = $this->previewActiveResolution($company->id, $environment, $documentType);
        $signing = $this->resolveSigning($company, $environment, $resolutionPreview, $documentType);

        $numbering = $this->allocateNumbering($company->id, $environment, $documentType);

        try {
            $context = $this->contextBuilder->buildFromKioskInvoice(
                $invoice,
                $company,
                $numbering['resolution'],
                $documentType,
                $environment,
                [
                    'prefix' => $numbering['prefix'],
                    'sequence' => $numbering['sequence'],
                    'number' => $numbering['number'],
                    'resolution_id' => $numbering['resolution_id'],
                ],
                $acquirer,
                $signing
            );

            $context['qr_url'] = $this->buildQrUrl($environment, null);

            $document = $this->emitter->emit($context);
        } catch (KioskEmissionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('KioskInvoice electronic emission failed', [
                'kiosk_invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            throw KioskEmissionUnavailableException::emitterFailure(
                'Could not build the electronic document for the kiosk invoice.',
                $e
            );
        }

        $document->qr_url = $this->buildQrUrl($environment, (string) $document->cufe_cude);
        $document->save();

        $invoice->electronic_document_id = $document->id;
        if ($acquirer !== null) {
            $invoice->acquirer_id = $acquirer->id;
        }
        $invoice->save();

        return $document;
    }

    /**
     * Returns a stable JSON-friendly snapshot for HTTP responses.
     *
     * @return array{
     *     id: int|null,
     *     status: string,
     *     document_type: string,
     *     dian_number: string|null,
     *     cufe_cude: string|null,
     *     qr_url: string|null,
     * }
     */
    public function summarise(ElectronicDocument $document): array
    {
        return [
            'id' => $document->id !== null ? (int) $document->id : null,
            'status' => (string) $document->status,
            'document_type' => (string) $document->document_type,
            'dian_number' => $document->dian_number !== null ? (string) $document->dian_number : null,
            'cufe_cude' => $document->cufe_cude !== null ? (string) $document->cufe_cude : null,
            'qr_url' => $document->qr_url !== null ? (string) $document->qr_url : null,
        ];
    }

    private function assertEnabled(): void
    {
        if (!$this->configBool('electronic-invoicing.enabled')) {
            throw KioskEmissionUnavailableException::disabled();
        }
    }

    private function resolveEnvironment(): string
    {
        $env = (string) ($this->configValue('electronic-invoicing.environment') ?: FiscalEnvironment::HABILITACION);
        FiscalEnvironment::assert($env);
        return $env;
    }

    private function resolveCompany(string $environment): CompanyFiscalProfile
    {
        $company = CompanyFiscalProfile::query()
            ->where('environment', $environment)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if ($company === null) {
            throw KioskEmissionUnavailableException::fiscalProfileMissing($environment);
        }
        return $company;
    }

    private function resolveAcquirer(
        KioskInvoice $invoice,
        ?array $payload,
        bool $required
    ): ?ElectronicDocumentAcquirer {
        if ($invoice->acquirer_id) {
            $acquirer = ElectronicDocumentAcquirer::find($invoice->acquirer_id);
            if ($acquirer !== null) {
                return $acquirer;
            }
        }

        if ($payload === null || $payload === []) {
            if ($required) {
                throw KioskEmissionInvalidPayloadException::missingAcquirer();
            }
            return null;
        }

        foreach (['document_type', 'document_number', 'legal_name'] as $field) {
            if (empty($payload[$field])) {
                throw KioskEmissionInvalidPayloadException::invalidAcquirer($field);
            }
        }

        $attributes = [
            'customer_id' => $invoice->customer_id,
            'document_type' => (string) $payload['document_type'],
            'document_number' => (string) $payload['document_number'],
            'dv' => isset($payload['dv']) && $payload['dv'] !== '' ? (int) $payload['dv'] : null,
            'legal_name' => (string) $payload['legal_name'],
            'tax_regime_code' => isset($payload['tax_regime_code']) ? (string) $payload['tax_regime_code'] : null,
            'tax_responsibilities' => $payload['tax_responsibilities'] ?? null,
            'address_line' => isset($payload['address_line']) ? (string) $payload['address_line'] : null,
            'city_code_dian' => isset($payload['city_code_dian']) ? (string) $payload['city_code_dian'] : null,
            'country_code' => isset($payload['country_code']) ? (string) $payload['country_code'] : 'CO',
            'email' => isset($payload['email']) ? (string) $payload['email'] : null,
            'phone' => isset($payload['phone']) ? (string) $payload['phone'] : null,
        ];

        return ElectronicDocumentAcquirer::create($attributes);
    }

    private function previewActiveResolution(int $companyId, string $environment, string $documentType): DianResolution
    {
        $resolution = DianResolution::query()
            ->where('company_id', $companyId)
            ->where('environment', $environment)
            ->where('document_type', $documentType)
            ->where('active', true)
            ->orderBy('id')
            ->first();
        if ($resolution === null) {
            throw KioskEmissionUnavailableException::resolutionMissing($environment, $documentType);
        }
        return $resolution;
    }

    private function allocateNumbering(int $companyId, string $environment, string $documentType): array
    {
        try {
            return $this->allocator->allocate($companyId, $environment, $documentType);
        } catch (NumberingException $e) {
            switch ($e->reason()) {
                case NumberingException::REASON_EXHAUSTED:
                    throw KioskEmissionUnavailableException::resolutionExhausted();
                case NumberingException::REASON_EXPIRED:
                    throw KioskEmissionUnavailableException::resolutionExpired();
                case NumberingException::REASON_NOT_YET_VALID:
                    throw KioskEmissionUnavailableException::resolutionNotYetValid();
                case NumberingException::REASON_MISSING:
                case NumberingException::REASON_INACTIVE:
                default:
                    throw KioskEmissionUnavailableException::resolutionMissing($environment, $documentType);
            }
        }
    }

    private function resolveSigning(
        CompanyFiscalProfile $company,
        string $environment,
        DianResolution $resolution,
        string $documentType
    ): array {
        if ($documentType === DocumentType::FEV) {
            if (empty($resolution->technical_key)) {
                throw KioskEmissionUnavailableException::resolutionMissing($environment, $documentType);
            }
            return [
                'clave_tecnica' => (string) $resolution->technical_key,
            ];
        }

        $credential = DianSoftwareCredential::query()
            ->where('company_id', $company->id)
            ->where('environment', $environment)
            ->first();
        if ($credential === null) {
            throw KioskEmissionUnavailableException::softwareCredentialMissing($environment);
        }

        try {
            $pin = $this->secrets->get((string) $credential->software_pin_secret_ref);
        } catch (SecretUnavailableException $e) {
            throw KioskEmissionUnavailableException::softwarePinUnresolvable($e);
        }

        return [
            'pin' => $pin,
            'software_id' => (string) $credential->software_id,
        ];
    }

    private function buildQrUrl(string $environment, ?string $cufe): ?string
    {
        if ($cufe === null || $cufe === '') {
            return null;
        }
        $key = $environment === FiscalEnvironment::PRODUCTION
            ? 'electronic-invoicing.qr.base_url_prod'
            : 'electronic-invoicing.qr.base_url_hab';
        $base = $this->configValue($key);
        if (!is_string($base) || $base === '') {
            return null;
        }
        return $base . $cufe;
    }

    private function configValue(string $key)
    {
        if (function_exists('config')) {
            return config($key);
        }
        return null;
    }

    private function configBool(string $key): bool
    {
        $value = $this->configValue($key);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }
}
