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
use App\Models\Reservation;
use App\Services\ElectronicInvoicing\Exceptions\NumberingException;
use App\Services\ElectronicInvoicing\Exceptions\ReservationEmissionException;
use App\Services\ElectronicInvoicing\Exceptions\ReservationEmissionInvalidPayloadException;
use App\Services\ElectronicInvoicing\Exceptions\ReservationEmissionUnavailableException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates the local emission of a FEV ElectronicDocument starting from a
 * checked-out Reservation. Network-free in this slice: it resolves fiscal
 * context, validates the acquirer payload, allocates a dian_number, and
 * delegates to DocumentEmitter to reach `ubl_built`.
 *
 * Errors are normalised into ReservationEmissionException subclasses so the
 * controller can decide whether to respond 422 (invalid payload) or attach a
 * structured `electronic_document_error` (configuration gaps).
 */
final class ReservationInvoiceEmissionService
{
    /** @var DocumentResolver */
    private $resolver;

    /** @var NumberingAllocator */
    private $allocator;

    /** @var ReservationEmissionContextBuilder */
    private $contextBuilder;

    /** @var DocumentEmitter */
    private $emitter;

    /** @var SecretManagerInterface */
    private $secrets;

    public function __construct(
        ?DocumentResolver $resolver = null,
        ?NumberingAllocator $allocator = null,
        ?ReservationEmissionContextBuilder $contextBuilder = null,
        ?DocumentEmitter $emitter = null,
        ?SecretManagerInterface $secrets = null
    ) {
        $this->resolver = $resolver ?: new DocumentResolver();
        $this->allocator = $allocator ?: new NumberingAllocator();
        $this->contextBuilder = $contextBuilder ?: new ReservationEmissionContextBuilder();
        $this->emitter = $emitter ?: new DocumentEmitter(new DocumentAssembler());
        $this->secrets = $secrets ?: new ArraySecretManager();
    }

    /**
     * Emit a FEV ElectronicDocument (state -> ubl_built) for the given
     * Reservation. Always returns a FEV: the spec mandates FEV at
     * reservation checkout.
     *
     * @param Reservation $reservation       Already checked-out reservation.
     * @param array|null  $acquirerPayload   Raw `acquirer` block from the request.
     */
    public function emitForReservation(Reservation $reservation, ?array $acquirerPayload = null): ElectronicDocument
    {
        $this->assertEnabled();

        $reservation->loadMissing(['additionalServices.additionalService', 'minibarCharges', 'room', 'customer']);

        $environment = $this->resolveEnvironment();
        $company = $this->resolveCompany($environment);

        $documentType = $this->resolver->forReservation($reservation);
        $acquirer = $this->resolveAcquirer($reservation, $acquirerPayload);

        $resolutionPreview = $this->previewActiveResolution($company->id, $environment, $documentType);
        $signing = $this->resolveSigning($company, $environment, $resolutionPreview, $documentType);

        $numbering = $this->allocateNumbering($company->id, $environment, $documentType);

        try {
            $context = $this->contextBuilder->buildFromReservation(
                $reservation,
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
        } catch (ReservationEmissionException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Reservation electronic emission failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            throw ReservationEmissionUnavailableException::emitterFailure(
                'Could not build the electronic document for the reservation checkout.',
                $e
            );
        }

        $document->qr_url = $this->buildQrUrl($environment, (string) $document->cufe_cude);
        $document->save();

        $reservation->electronic_document_id = $document->id;
        $reservation->save();

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
            throw ReservationEmissionUnavailableException::disabled();
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
            throw ReservationEmissionUnavailableException::fiscalProfileMissing($environment);
        }
        return $company;
    }

    private function resolveAcquirer(
        Reservation $reservation,
        ?array $payload
    ): ElectronicDocumentAcquirer {
        if ($payload === null || $payload === []) {
            throw ReservationEmissionInvalidPayloadException::missingAcquirer();
        }
        foreach (['document_type', 'document_number', 'legal_name'] as $field) {
            if (empty($payload[$field])) {
                throw ReservationEmissionInvalidPayloadException::invalidAcquirer($field);
            }
        }

        $attributes = [
            'customer_id' => $reservation->customer_id,
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
            throw ReservationEmissionUnavailableException::resolutionMissing($environment, $documentType);
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
                    throw ReservationEmissionUnavailableException::resolutionExhausted();
                case NumberingException::REASON_EXPIRED:
                    throw ReservationEmissionUnavailableException::resolutionExpired();
                case NumberingException::REASON_NOT_YET_VALID:
                    throw ReservationEmissionUnavailableException::resolutionNotYetValid();
                case NumberingException::REASON_MISSING:
                case NumberingException::REASON_INACTIVE:
                default:
                    throw ReservationEmissionUnavailableException::resolutionMissing($environment, $documentType);
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
                throw ReservationEmissionUnavailableException::resolutionMissing($environment, $documentType);
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
            throw ReservationEmissionUnavailableException::softwareCredentialMissing($environment);
        }

        try {
            $pin = $this->secrets->get((string) $credential->software_pin_secret_ref);
        } catch (SecretUnavailableException $e) {
            throw ReservationEmissionUnavailableException::softwarePinUnresolvable($e);
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
