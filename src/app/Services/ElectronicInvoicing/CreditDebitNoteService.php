<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
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
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteException;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteInvalidPayloadException;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteUnavailableException;
use App\Services\ElectronicInvoicing\Exceptions\NumberingException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates the local emission of NC (Nota Crédito) and ND (Nota Débito)
 * ElectronicDocuments that reference an existing FEV or DEE POS.
 *
 * Network-free in this slice: the resulting document stays at `ubl_built`.
 * All error paths are normalised into CreditDebitNoteException subclasses so
 * the controller can map them deterministically to HTTP responses.
 *
 * Validations performed:
 *  - Parent document exists.
 *  - Parent type is fev or dee_pos.
 *  - Parent status is referenceable (production should require
 *    `dian_accepted`; this development slice also accepts `ubl_built` and
 *    `xades_signed` so the flow is testable without DIAN).
 *  - Parent has a CUFE/CUDE.
 *  - Company match (no cross-company NC/ND).
 *  - Acquirer present (inherited from parent or supplied in request).
 *  - At least one line with positive line_total.
 *  - Totals consistent (line_extension_amount > 0).
 *  - NC requires `discrepancy_code` (DIAN catálogo tabla 13.2.4).
 *  - ND requires a `reason` (concept of the surcharge).
 *
 * On success it allocates a new `dian_number` from the NC/ND resolution
 * for the same company and environment, stores `references_document_id`
 * pointing at the parent and delegates the UBL build / CUFE / persistence
 * to DocumentEmitter via the shared context contract.
 */
final class CreditDebitNoteService
{
    /** Statuses the slice considers safe enough to derive NC/ND from. */
    public const REFERENCEABLE_STATUSES = [
        DocumentStatus::UBL_BUILT,
        DocumentStatus::XADES_SIGNED,
        DocumentStatus::SENT_TO_DIAN,
        DocumentStatus::DIAN_VALIDATING,
        DocumentStatus::DIAN_TRACK_RECEIVED,
        DocumentStatus::DIAN_ACCEPTED,
    ];

    /** @var NumberingAllocator */
    private $allocator;

    /** @var DocumentEmitter */
    private $emitter;

    /** @var DocumentAssembler */
    private $assembler;

    /** @var SecretManagerInterface */
    private $secrets;

    public function __construct(
        ?NumberingAllocator $allocator = null,
        ?DocumentEmitter $emitter = null,
        ?DocumentAssembler $assembler = null,
        ?SecretManagerInterface $secrets = null
    ) {
        $this->allocator = $allocator ?: new NumberingAllocator();
        $this->assembler = $assembler ?: new DocumentAssembler();
        $this->emitter = $emitter ?: new DocumentEmitter($this->assembler);
        $this->secrets = $secrets ?: new ArraySecretManager();
    }

    public function emitCreditNote(int $parentId, array $payload): ElectronicDocument
    {
        return $this->emit(DocumentType::NC, $parentId, $payload);
    }

    public function emitDebitNote(int $parentId, array $payload): ElectronicDocument
    {
        return $this->emit(DocumentType::ND, $parentId, $payload);
    }

    /**
     * @param string $documentType One of DocumentType::NC | DocumentType::ND
     * @param int    $parentId     Parent ElectronicDocument id
     * @param array  $payload      Request body (see class docblock)
     */
    public function emit(string $documentType, int $parentId, array $payload): ElectronicDocument
    {
        DocumentType::assert($documentType);
        if (!DocumentType::isReferencing($documentType)) {
            throw CreditDebitNoteInvalidPayloadException::parentTypeNotReferenceable($documentType);
        }

        $this->assertEnabled();

        $parent = ElectronicDocument::query()->find($parentId);
        if ($parent === null) {
            throw CreditDebitNoteInvalidPayloadException::parentNotFound($parentId);
        }
        $this->assertParentReferenceable($parent);

        $company = CompanyFiscalProfile::query()->find($parent->company_id);
        if ($company === null) {
            throw CreditDebitNoteUnavailableException::fiscalProfileMissing();
        }

        $environment = (string) $parent->environment;
        FiscalEnvironment::assert($environment);

        $lines = $this->validateLines($payload['lines'] ?? null);
        $totals = $this->validateTotals($payload['totals'] ?? null, $lines);

        if ($documentType === DocumentType::NC) {
            if (empty($payload['discrepancy_code'])) {
                throw CreditDebitNoteInvalidPayloadException::discrepancyCodeRequired();
            }
        } else {
            $reason = trim((string) ($payload['reason'] ?? ''));
            if ($reason === '') {
                throw CreditDebitNoteInvalidPayloadException::reasonRequired();
            }
        }

        $acquirer = $this->resolveAcquirer($parent, $payload['acquirer'] ?? null);

        $resolutionPreview = $this->previewActiveResolution((int) $company->id, $environment, $documentType);
        $signing = $this->resolveSigning($company, $environment, $resolutionPreview, $documentType);

        $numbering = $this->allocateNumbering((int) $company->id, $environment, $documentType);

        $issuedAt = Carbon::now();
        $references = [
            $this->buildReference($parent, $payload),
        ];

        try {
            $context = [
                'company' => $company,
                'document_type' => $documentType,
                'environment' => $environment,
                'numbering' => [
                    'prefix' => $numbering['prefix'],
                    'sequence' => $numbering['sequence'],
                    'number' => $numbering['number'],
                    'resolution_id' => $numbering['resolution_id'],
                ],
                'acquirer' => $acquirer,
                'acquirer_id' => (int) $acquirer->id,
                'issued_at' => $issuedAt,
                'currency' => (string) ($parent->currency_code ?: 'COP'),
                'lines' => $lines,
                'totals' => [
                    'line_extension_amount' => $this->money($totals['line_extension_amount']),
                    'tax_exclusive_amount' => $this->money($totals['tax_exclusive_amount']),
                    'tax_inclusive_amount' => $this->money($totals['tax_inclusive_amount']),
                    'payable_amount' => $this->money($totals['payable_amount']),
                ],
                'taxes' => $totals['taxes'] ?? [],
                'payment' => [
                    'means_code' => (string) ($parent->payment_means_code ?: '10'),
                    'terms_code' => (string) ($parent->payment_terms ?: '1'),
                ],
                'cufe_signing' => $signing,
                'software_credential' => $this->softwareCredentialBlock($signing),
                'source_meta' => [
                    'source_type' => $documentType === DocumentType::NC ? 'credit_note' : 'debit_note',
                    'source_id' => (int) $parent->id,
                ],
                'references_document_id' => (int) $parent->id,
                'references' => $references,
                'notes' => $this->notesBlock($documentType, $payload, $parent),
                'qr_url' => null,
            ];

            $document = $this->emitter->emit($context);
        } catch (CreditDebitNoteException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('CreditDebitNote emission failed', [
                'parent_id' => $parent->id,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);
            throw CreditDebitNoteUnavailableException::emitterFailure(
                'Could not build the NC/ND electronic document.',
                $e
            );
        }

        // Reinforce the reference fields in case the assembler mutated them.
        $document->references_document_id = (int) $parent->id;
        $document->source_type = $documentType === DocumentType::NC ? 'credit_note' : 'debit_note';
        $document->source_id = (int) $parent->id;
        $document->save();

        return $document;
    }

    public function summarise(ElectronicDocument $document): array
    {
        return [
            'id' => $document->id !== null ? (int) $document->id : null,
            'status' => (string) $document->status,
            'document_type' => (string) $document->document_type,
            'dian_number' => $document->dian_number !== null ? (string) $document->dian_number : null,
            'cufe_cude' => $document->cufe_cude !== null ? (string) $document->cufe_cude : null,
            'qr_url' => $document->qr_url !== null ? (string) $document->qr_url : null,
            'references_document_id' => $document->references_document_id !== null
                ? (int) $document->references_document_id
                : null,
        ];
    }

    private function assertEnabled(): void
    {
        if (!$this->configBool('electronic-invoicing.enabled')) {
            throw CreditDebitNoteUnavailableException::disabled();
        }
    }

    private function assertParentReferenceable(ElectronicDocument $parent): void
    {
        $type = (string) $parent->document_type;
        if (!in_array($type, [DocumentType::FEV, DocumentType::DEE_POS], true)) {
            throw CreditDebitNoteInvalidPayloadException::parentTypeNotReferenceable($type);
        }
        $status = (string) $parent->status;
        if (!in_array($status, self::REFERENCEABLE_STATUSES, true)) {
            throw CreditDebitNoteInvalidPayloadException::parentStatusNotReferenceable($status);
        }
        if (empty($parent->cufe_cude)) {
            throw CreditDebitNoteInvalidPayloadException::parentHasNoCufe();
        }
        if (empty($parent->dian_number)) {
            throw CreditDebitNoteInvalidPayloadException::parentHasNoCufe();
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function validateLines($lines): array
    {
        if (!is_array($lines) || $lines === []) {
            throw CreditDebitNoteInvalidPayloadException::invalidLines();
        }
        $out = [];
        $sequence = 1;
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw CreditDebitNoteInvalidPayloadException::invalidLines(
                    sprintf('lines.%s must be an object.', $index)
                );
            }
            $description = trim((string) ($line['description'] ?? ''));
            if ($description === '') {
                throw CreditDebitNoteInvalidPayloadException::invalidLines(
                    sprintf('lines.%s.description is required.', $index)
                );
            }
            $quantity = (float) ($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw CreditDebitNoteInvalidPayloadException::invalidLines(
                    sprintf('lines.%s.quantity must be positive.', $index)
                );
            }
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineTotal = isset($line['line_total']) ? (float) $line['line_total'] : round($unitPrice * $quantity, 2);
            if ($lineTotal <= 0) {
                throw CreditDebitNoteInvalidPayloadException::invalidLines(
                    sprintf('lines.%s.line_total must be positive.', $index)
                );
            }
            $taxAmount = isset($line['tax_amount']) ? (float) $line['tax_amount'] : 0.0;
            $taxableAmount = isset($line['taxable_amount']) ? (float) $line['taxable_amount'] : $lineTotal;

            $out[] = [
                'sequence' => (string) $sequence,
                'description' => $description,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit_code' => (string) ($line['unit_code'] ?? 'NIU'),
                'unit_price' => $this->money($unitPrice),
                'line_total' => $this->money($lineTotal),
                'taxable_amount' => $this->money($taxableAmount),
                'tax_amount' => $this->money($taxAmount),
                'tax_percent' => isset($line['tax_percent']) ? (string) $line['tax_percent'] : '0.00',
                'tax_scheme_code' => (string) ($line['tax_scheme_code'] ?? '01'),
                'tax_scheme_name' => (string) ($line['tax_scheme_name'] ?? 'IVA'),
            ];
            $sequence++;
        }
        return $out;
    }

    /**
     * @param array|null $totals
     * @param array      $lines  Normalised lines from validateLines()
     * @return array{
     *     line_extension_amount: float,
     *     tax_exclusive_amount: float,
     *     tax_inclusive_amount: float,
     *     payable_amount: float,
     *     taxes: array,
     * }
     */
    private function validateTotals($totals, array $lines): array
    {
        $defaultBase = 0.0;
        $defaultTax = 0.0;
        foreach ($lines as $line) {
            $defaultBase += (float) $line['taxable_amount'];
            $defaultTax += (float) $line['tax_amount'];
        }
        if ($defaultBase <= 0) {
            throw CreditDebitNoteInvalidPayloadException::invalidTotals(
                'Aggregated taxable amount must be positive.'
            );
        }

        $totals = is_array($totals) ? $totals : [];
        $base = isset($totals['line_extension_amount']) ? (float) $totals['line_extension_amount'] : $defaultBase;
        $taxExcl = isset($totals['tax_exclusive_amount']) ? (float) $totals['tax_exclusive_amount'] : $base;
        $taxIncl = isset($totals['tax_inclusive_amount']) ? (float) $totals['tax_inclusive_amount'] : ($base + $defaultTax);
        $payable = isset($totals['payable_amount']) ? (float) $totals['payable_amount'] : $taxIncl;

        if ($base <= 0 || $payable <= 0) {
            throw CreditDebitNoteInvalidPayloadException::invalidTotals(
                'line_extension_amount and payable_amount must be positive.'
            );
        }

        return [
            'line_extension_amount' => $base,
            'tax_exclusive_amount' => $taxExcl,
            'tax_inclusive_amount' => $taxIncl,
            'payable_amount' => $payable,
            'taxes' => $totals['taxes'] ?? [],
        ];
    }

    private function resolveAcquirer(ElectronicDocument $parent, $payload): ElectronicDocumentAcquirer
    {
        if (is_array($payload) && $payload !== []) {
            foreach (['document_type', 'document_number', 'legal_name'] as $field) {
                if (empty($payload[$field])) {
                    throw CreditDebitNoteInvalidPayloadException::invalidAcquirer($field);
                }
            }
            return ElectronicDocumentAcquirer::create([
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
            ]);
        }

        if ($parent->acquirer_id) {
            $acquirer = ElectronicDocumentAcquirer::find($parent->acquirer_id);
            if ($acquirer !== null) {
                return $acquirer;
            }
        }

        throw CreditDebitNoteInvalidPayloadException::missingAcquirer();
    }

    private function buildReference(ElectronicDocument $parent, array $payload): array
    {
        $reference = [
            'cufe' => (string) $parent->cufe_cude,
            'number' => (string) $parent->dian_number,
            'issue_date' => $parent->issue_date instanceof \DateTimeInterface
                ? $parent->issue_date->format('Y-m-d')
                : Carbon::parse((string) $parent->issue_date)->format('Y-m-d'),
            'scheme_id' => '2',
            'scheme_name' => 'CUFE-SHA384',
        ];
        if (!empty($payload['discrepancy_code'])) {
            $reference['discrepancy_code'] = (string) $payload['discrepancy_code'];
        }
        $description = trim((string) ($payload['discrepancy_description'] ?? $payload['reason'] ?? ''));
        if ($description !== '') {
            $reference['discrepancy_description'] = $description;
        }
        return $reference;
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
            throw CreditDebitNoteUnavailableException::resolutionMissing($environment, $documentType);
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
                    throw CreditDebitNoteUnavailableException::resolutionExhausted();
                case NumberingException::REASON_EXPIRED:
                    throw CreditDebitNoteUnavailableException::resolutionExpired();
                case NumberingException::REASON_NOT_YET_VALID:
                    throw CreditDebitNoteUnavailableException::resolutionNotYetValid();
                case NumberingException::REASON_MISSING:
                case NumberingException::REASON_INACTIVE:
                default:
                    throw CreditDebitNoteUnavailableException::resolutionMissing($environment, $documentType);
            }
        }
    }

    private function resolveSigning(
        CompanyFiscalProfile $company,
        string $environment,
        DianResolution $resolution,
        string $documentType
    ): array {
        // NC/ND firman con PIN (software_security_code), no con clave técnica.
        $credential = DianSoftwareCredential::query()
            ->where('company_id', $company->id)
            ->where('environment', $environment)
            ->first();
        if ($credential === null) {
            throw CreditDebitNoteUnavailableException::softwareCredentialMissing($environment);
        }
        try {
            $pin = $this->secrets->get((string) $credential->software_pin_secret_ref);
        } catch (SecretUnavailableException $e) {
            throw CreditDebitNoteUnavailableException::softwarePinUnresolvable($e);
        }
        return [
            'pin' => $pin,
            'software_id' => (string) $credential->software_id,
        ];
    }

    private function softwareCredentialBlock(array $signing): ?array
    {
        if (empty($signing['software_id']) || empty($signing['pin'])) {
            return null;
        }
        return [
            'software_id' => (string) $signing['software_id'],
            'pin' => (string) $signing['pin'],
        ];
    }

    private function notesBlock(string $documentType, array $payload, ElectronicDocument $parent): string
    {
        $reason = trim((string) ($payload['reason'] ?? $payload['discrepancy_description'] ?? ''));
        $label = $documentType === DocumentType::NC ? 'Nota Crédito' : 'Nota Débito';
        $parts = [sprintf('%s referencia %s', $label, (string) $parent->dian_number)];
        if ($reason !== '') {
            $parts[] = $reason;
        }
        return implode(' — ', $parts);
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
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
