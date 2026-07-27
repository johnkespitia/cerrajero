<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\KioskInvoice;
use App\Services\ElectronicInvoicing\Exceptions\IncompleteEmissionPayloadException;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Translates a normalised emission context (Reservation, KioskInvoice or any
 * other internal source) into the canonical array consumed by
 * App\Infrastructure\ElectronicInvoicing\Ubl\* builders AND by
 * App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator.
 *
 * The assembler is intentionally side-effect-free: it does not allocate
 * numbers, persist anything or call DIAN. It only reshapes data.
 *
 * Required shape of $context:
 *  - company: CompanyFiscalProfile
 *  - document_type: DocumentType::*
 *  - environment: FiscalEnvironment::*
 *  - numbering: ['prefix', 'sequence', 'number', 'resolution_id']
 *  - issued_at: DateTimeInterface
 *  - currency: string (ISO-4217)
 *  - lines: non-empty list, each with sequence, description, quantity, unit_price, line_total
 *  - totals: {line_extension_amount, tax_exclusive_amount, tax_inclusive_amount, payable_amount}
 *  - cufe_signing: {clave_tecnica? (FEV), pin? (NC/ND/DEE POS)}
 *
 * Optional:
 *  - acquirer: ElectronicDocumentAcquirer (mandatory for FEV/NC/ND)
 *  - taxes: list of tax breakdowns
 *  - payment: {means_code, terms_code, payment_due_date?}
 *  - references: list (for NC / ND)
 *  - dian_extensions: snapshot to embed under sts:DianExtensions
 */
final class DocumentAssembler
{
    public function assemble(array $context): array
    {
        $this->assertContext($context);

        $documentType = (string) $context['document_type'];
        $environment = (string) $context['environment'];

        /** @var CompanyFiscalProfile $company */
        $company = $context['company'];
        $numbering = $context['numbering'];

        /** @var DateTimeInterface $issuedAt */
        $issuedAt = $context['issued_at'];
        $issuedCarbon = Carbon::instance($issuedAt);
        $issueDate = $issuedCarbon->format('Y-m-d');
        $issueTime = $issuedCarbon->format('H:i:s\-05:00');

        $environmentCode = $this->environmentCode($environment);
        $currency = (string) ($context['currency'] ?? 'COP');

        $totals = $context['totals'];
        $taxes = $context['taxes'] ?? [];
        $lines = $context['lines'];

        $supplier = $this->supplierFromCompany($company);
        $customer = $this->customerFromAcquirer($context['acquirer'] ?? null);

        if (in_array($documentType, [DocumentType::FEV, DocumentType::NC, DocumentType::ND], true) && $customer === null) {
            throw IncompleteEmissionPayloadException::for('acquirer');
        }

        $document = [
            'type' => $documentType,
            'prefix' => (string) $numbering['prefix'],
            'sequence' => (int) $numbering['sequence'],
            'number' => (string) $numbering['number'],
            'issue_date' => $issueDate,
            'issue_time' => $issueTime,
            'currency' => $currency,
            'environment' => $environmentCode,
            'cufe' => str_repeat('0', 96),
        ];

        $payload = [
            'document' => $document,
            'supplier' => $supplier,
            'lines' => $this->normaliseLines($lines, $currency),
            'totals' => $this->normaliseTotals($totals),
        ];

        if ($customer !== null) {
            $payload['customer'] = $customer;
        }
        if ($taxes !== []) {
            $payload['taxes'] = $taxes;
        }
        if (!empty($context['payment'])) {
            $payload['payment'] = $context['payment'];
        }
        if (!empty($context['references'])) {
            $payload['references'] = $context['references'];
        }
        if (!empty($context['dian_extensions'])) {
            $payload['dian_extensions'] = $context['dian_extensions'];
        }

        $payload['cufe_fields'] = $this->buildCufeFields(
            $documentType,
            $environmentCode,
            $document,
            $totals,
            $taxes,
            $supplier,
            $customer,
            $context['cufe_signing'] ?? []
        );

        return $payload;
    }

    /**
     * Convenience for callers holding a KioskInvoice. Combines invoice-level
     * data with an explicit context that carries fiscal breakdowns the
     * KioskInvoice model does not own yet (lines/taxes/totals snapshot,
     * acquirer, numbering, signing keys). Missing pieces raise
     * IncompleteEmissionPayloadException so the caller never accidentally
     * ships a partial document.
     */
    public function fromKioskInvoice(KioskInvoice $invoice, array $context): array
    {
        if (empty($context['source_meta'])) {
            $context['source_meta'] = [
                'source_type' => 'kiosk_invoice',
                'source_id' => (int) $invoice->id,
            ];
        }
        if (empty($context['issued_at'])) {
            $context['issued_at'] = Carbon::instance($invoice->created_at ?? Carbon::now());
        }

        return $this->assemble($context);
    }

    private function assertContext(array $context): void
    {
        foreach (['company', 'document_type', 'environment', 'numbering', 'issued_at', 'totals', 'lines'] as $key) {
            if (!array_key_exists($key, $context) || $context[$key] === null) {
                throw IncompleteEmissionPayloadException::for($key);
            }
        }
        DocumentType::assert((string) $context['document_type']);
        FiscalEnvironment::assert((string) $context['environment']);

        if (!($context['company'] instanceof CompanyFiscalProfile)) {
            throw IncompleteEmissionPayloadException::for('company', 'not a CompanyFiscalProfile');
        }
        if (!($context['issued_at'] instanceof DateTimeInterface)) {
            throw IncompleteEmissionPayloadException::for('issued_at', 'not a DateTimeInterface');
        }
        if (!is_array($context['lines']) || $context['lines'] === []) {
            throw IncompleteEmissionPayloadException::for('lines', 'missing or empty');
        }
        if (!is_array($context['totals'])) {
            throw IncompleteEmissionPayloadException::for('totals', 'not an object');
        }
        $numbering = $context['numbering'];
        if (!is_array($numbering)) {
            throw IncompleteEmissionPayloadException::for('numbering', 'not an object');
        }
        foreach (['prefix', 'sequence', 'number'] as $k) {
            if (!array_key_exists($k, $numbering) || $numbering[$k] === '' || $numbering[$k] === null) {
                throw IncompleteEmissionPayloadException::for('numbering.' . $k);
            }
        }
        $this->assertCufeSigning((string) $context['document_type'], $context['cufe_signing'] ?? []);
    }

    private function assertCufeSigning(string $documentType, array $signing): void
    {
        if ($documentType === DocumentType::FEV) {
            if (empty($signing['clave_tecnica'])) {
                throw IncompleteEmissionPayloadException::for('cufe_signing.clave_tecnica');
            }
            return;
        }
        if (empty($signing['pin'])) {
            throw IncompleteEmissionPayloadException::for('cufe_signing.pin');
        }
    }

    private function environmentCode(string $environment): string
    {
        return $environment === FiscalEnvironment::PRODUCTION ? '1' : '2';
    }

    private function supplierFromCompany(CompanyFiscalProfile $company): array
    {
        $nit = (string) $company->nit;
        if ($nit === '') {
            throw IncompleteEmissionPayloadException::for('company.nit');
        }
        $name = (string) ($company->legal_name ?? $company->trade_name ?? '');
        if ($name === '') {
            throw IncompleteEmissionPayloadException::for('company.legal_name');
        }

        return [
            'nit' => $nit,
            'verification_digit' => $company->dv !== null ? (string) $company->dv : null,
            'name' => $name,
            'commercial_name' => (string) ($company->trade_name ?? $name),
            'address_line' => (string) ($company->address_line ?? ''),
            'city_code' => (string) ($company->city_code_dian ?? ''),
            'country_code' => (string) ($company->country_code ?? 'CO'),
            'id_type' => '31',
            'tax_scheme_code' => '01',
            'tax_scheme_name' => 'IVA',
            'tax_regime_code' => (string) ($company->tax_regime_code ?? '49'),
        ];
    }

    private function customerFromAcquirer(?ElectronicDocumentAcquirer $acquirer): ?array
    {
        if ($acquirer === null) {
            return null;
        }
        return [
            'id' => (string) $acquirer->document_number,
            'id_type' => (string) ($acquirer->document_type ?? '13'),
            'verification_digit' => $acquirer->dv !== null ? (string) $acquirer->dv : null,
            'name' => (string) ($acquirer->legal_name ?? ''),
            'address_line' => (string) ($acquirer->address_line ?? ''),
            'city_code' => (string) ($acquirer->city_code_dian ?? ''),
            'country_code' => (string) ($acquirer->country_code ?? 'CO'),
            'tax_scheme_code' => '01',
            'tax_scheme_name' => 'IVA',
            'tax_regime_code' => (string) ($acquirer->tax_regime_code ?? '49'),
            'email' => (string) ($acquirer->email ?? ''),
        ];
    }

    private function normaliseLines(array $lines, string $currency): array
    {
        $out = [];
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw IncompleteEmissionPayloadException::for('lines.' . $index, 'not an object');
            }
            foreach (['sequence', 'description', 'quantity', 'unit_price', 'line_total'] as $required) {
                if (!array_key_exists($required, $line) || $line[$required] === null || $line[$required] === '') {
                    throw IncompleteEmissionPayloadException::for('lines.' . $index . '.' . $required);
                }
            }
            $line['unit_code'] = $line['unit_code'] ?? 'NIU';
            $line['currency'] = $line['currency'] ?? $currency;
            $out[] = $line;
        }
        return $out;
    }

    private function normaliseTotals(array $totals): array
    {
        foreach (['line_extension_amount', 'tax_exclusive_amount', 'tax_inclusive_amount', 'payable_amount'] as $k) {
            if (!array_key_exists($k, $totals) || $totals[$k] === '' || $totals[$k] === null) {
                throw IncompleteEmissionPayloadException::for('totals.' . $k);
            }
        }
        return $totals;
    }

    private function buildCufeFields(
        string $documentType,
        string $environmentCode,
        array $document,
        array $totals,
        array $taxes,
        array $supplier,
        ?array $customer,
        array $signing
    ): array {
        $taxMap = $this->taxMap($taxes);
        $fields = [
            'num_doc' => $document['number'],
            'fec_doc' => $document['issue_date'],
            'hora_doc' => $document['issue_time'],
            'val_doc' => $this->moneyString($totals['line_extension_amount']),
            'cod_imp_1' => '01', 'val_imp_1' => $this->moneyString($taxMap['01'] ?? '0.00'),
            'cod_imp_2' => '04', 'val_imp_2' => $this->moneyString($taxMap['04'] ?? '0.00'),
            'cod_imp_3' => '03', 'val_imp_3' => $this->moneyString($taxMap['03'] ?? '0.00'),
            'val_imp_total' => $this->moneyString($totals['payable_amount']),
            'nit_ofe' => $supplier['nit'],
            'num_adq' => $customer['id'] ?? '222222222222',
            'tipo_ambiente' => $environmentCode,
        ];

        if ($documentType === DocumentType::FEV) {
            $fields['clave_tecnica'] = (string) $signing['clave_tecnica'];
            return $this->reorderFevFields($fields);
        }

        $fields['pin'] = (string) $signing['pin'];
        return $this->reorderNonFevFields($fields);
    }

    private function reorderFevFields(array $fields): array
    {
        $order = [
            'num_doc', 'fec_doc', 'hora_doc', 'val_doc',
            'cod_imp_1', 'val_imp_1', 'cod_imp_2', 'val_imp_2', 'cod_imp_3', 'val_imp_3',
            'val_imp_total', 'nit_ofe', 'num_adq', 'clave_tecnica', 'tipo_ambiente',
        ];
        $out = [];
        foreach ($order as $key) {
            $out[$key] = $fields[$key];
        }
        return $out;
    }

    private function reorderNonFevFields(array $fields): array
    {
        $order = [
            'num_doc', 'fec_doc', 'hora_doc', 'val_doc',
            'cod_imp_1', 'val_imp_1', 'cod_imp_2', 'val_imp_2', 'cod_imp_3', 'val_imp_3',
            'val_imp_total', 'nit_ofe', 'num_adq', 'pin', 'tipo_ambiente',
        ];
        $out = [];
        foreach ($order as $key) {
            $out[$key] = $fields[$key];
        }
        return $out;
    }

    private function taxMap(array $taxes): array
    {
        $map = [];
        foreach ($taxes as $tax) {
            if (!is_array($tax) || empty($tax['code'])) {
                continue;
            }
            $code = (string) $tax['code'];
            $map[$code] = ($map[$code] ?? 0) + (float) ($tax['tax_amount'] ?? 0);
        }
        $strings = [];
        foreach ($map as $code => $amount) {
            $strings[$code] = number_format($amount, 2, '.', '');
        }
        return $strings;
    }

    private function moneyString($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
