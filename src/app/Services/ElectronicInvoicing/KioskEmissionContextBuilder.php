<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\KioskInvoice;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionInvalidPayloadException;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Adapter that turns a stored KioskInvoice into the canonical emission
 * context expected by DocumentAssembler / DocumentEmitter.
 *
 * Responsibilities:
 *  - Read the per-line fiscal snapshot captured at sale time
 *    (`fiscal_*` columns + `fiscal_snapshot`) when present, so subsequent
 *    edits to `KioskProduct.tax_id` or `Tax.rate` cannot mutate the
 *    historical breakdown.
 *  - Fall back to the legacy on-the-fly derivation (reverse-charge from
 *    gross price using `product.tax.rate`) only for legacy rows that were
 *    created before the snapshot column existed.
 *  - Aggregate totals at document level.
 *  - Fill numbering, signing and source_meta segments.
 */
final class KioskEmissionContextBuilder
{
    public const TAX_CODE_IVA = '01';
    public const TAX_NAME_IVA = 'IVA';

    /**
     * @param array{
     *     prefix: string,
     *     sequence: int|string,
     *     number: string,
     *     resolution_id?: int
     * } $numbering
     * @param array{
     *     clave_tecnica?: string,
     *     pin?: string,
     *     software_id?: string,
     * } $signing
     */
    public function buildFromKioskInvoice(
        KioskInvoice $invoice,
        CompanyFiscalProfile $company,
        DianResolution $resolution,
        string $documentType,
        string $environment,
        array $numbering,
        ?ElectronicDocumentAcquirer $acquirer,
        array $signing,
        ?DateTimeInterface $issuedAt = null
    ): array {
        DocumentType::assert($documentType);
        FiscalEnvironment::assert($environment);

        $details = $invoice->relationLoaded('details')
            ? $invoice->details
            : $invoice->details()->with('kiosk_unit.product.tax')->get();

        if ($details === null || $details->count() === 0) {
            throw KioskEmissionInvalidPayloadException::invalidLines();
        }

        $lines = [];
        $totalBase = 0.0;
        $totalTax = 0.0;
        $taxBucket = [];
        foreach ($details as $index => $detail) {
            $line = $this->lineFromDetail($detail, $index + 1);
            $lines[] = $line;

            $base = (float) ($line['taxable_amount'] ?? $line['line_total']);
            $totalBase += $base;
            $totalTax += (float) ($line['tax_amount'] ?? 0);

            if (!empty($line['tax_amount'])) {
                $code = (string) $line['tax_scheme_code'];
                $percent = (string) $line['tax_percent'];
                $key = $code . ':' . $percent;
                if (!isset($taxBucket[$key])) {
                    $taxBucket[$key] = [
                        'code' => $code,
                        'name' => $line['tax_scheme_name'] ?? self::TAX_NAME_IVA,
                        'percent' => $percent,
                        'taxable_amount' => 0.0,
                        'tax_amount' => 0.0,
                    ];
                }
                $taxBucket[$key]['taxable_amount'] += (float) $line['taxable_amount'];
                $taxBucket[$key]['tax_amount'] += (float) $line['tax_amount'];
            }
        }
        $totalGross = $totalBase + $totalTax;

        $issuedAt = $issuedAt ?: ($invoice->updated_at ?? $invoice->created_at ?? Carbon::now());

        $context = [
            'company' => $company,
            'document_type' => $documentType,
            'environment' => $environment,
            'numbering' => $numbering,
            'acquirer' => $acquirer,
            'acquirer_id' => $acquirer !== null ? (int) $acquirer->id : null,
            'issued_at' => $issuedAt,
            'currency' => 'COP',
            'lines' => $lines,
            'totals' => [
                'line_extension_amount' => $this->money($totalBase),
                'tax_exclusive_amount' => $this->money($totalBase),
                'tax_inclusive_amount' => $this->money($totalBase + $totalTax),
                'payable_amount' => $this->money($totalGross),
            ],
            'taxes' => $this->normaliseTaxes($taxBucket),
            'payment' => $this->paymentBlock($invoice),
            'cufe_signing' => $signing,
            'software_credential' => $this->softwareCredentialBlock($signing),
            'source_meta' => [
                'source_type' => 'kiosk_invoice',
                'source_id' => (int) $invoice->id,
            ],
            'notes' => $this->notesBlock($invoice),
        ];

        return $context;
    }

    private function lineFromDetail($detail, int $sequence): array
    {
        if ($detail instanceof \App\Models\KioskInvoiceDetail && $detail->hasFiscalSnapshot()) {
            return $this->lineFromSnapshot($detail, $sequence);
        }
        return $this->lineFromLegacyDetail($detail, $sequence);
    }

    /**
     * Snapshot path: trust the stored fiscal_* columns and ignore any later
     * mutations on the underlying product/tax rows.
     */
    private function lineFromSnapshot($detail, int $sequence): array
    {
        $unit = $detail->kiosk_unit ?? null;
        $product = $unit !== null ? $unit->product : null;

        $snapshot = is_array($detail->fiscal_snapshot) ? $detail->fiscal_snapshot : [];
        $description = $snapshot['product_name']
            ?? ($product->name ?? null)
            ?? $this->describeProduct($product, $unit, $detail);

        $base = (float) $detail->fiscal_base_amount;
        $taxAmount = (float) ($detail->fiscal_tax_amount ?? 0);
        $rate = (float) ($detail->fiscal_tax_rate ?? 0);
        $quantity = (float) ($detail->fiscal_quantity ?? KioskFiscalSnapshotBuilder::DEFAULT_QUANTITY);
        if ($quantity <= 0) {
            $quantity = KioskFiscalSnapshotBuilder::DEFAULT_QUANTITY;
        }
        $unitCode = (string) ($detail->fiscal_unit_measure_dian ?: KioskFiscalSnapshotBuilder::DEFAULT_UNIT_MEASURE);
        $unitPrice = $detail->fiscal_unit_price !== null
            ? (float) $detail->fiscal_unit_price
            : ($quantity > 0 ? $base / $quantity : $base);

        $line = [
            'sequence' => (string) $sequence,
            'description' => (string) $description,
            'quantity' => $this->quantity($quantity),
            'unit_code' => $unitCode,
            'unit_price' => $this->money($unitPrice),
            'line_total' => $this->money($base),
        ];

        if ($taxAmount > 0 || $rate > 0) {
            $line['taxable_amount'] = $this->money($base);
            $line['tax_amount'] = $this->money($taxAmount);
            $line['tax_percent'] = $this->percent($rate);
            $line['tax_scheme_code'] = (string) ($detail->fiscal_tax_code_dian ?: self::TAX_CODE_IVA);
            $line['tax_scheme_name'] = (string) ($detail->fiscal_tax_name ?: self::TAX_NAME_IVA);
        }

        return $line;
    }

    /**
     * Legacy path: derive the breakdown from the gross price on the fly.
     * Used only for rows persisted before the fiscal snapshot migration.
     */
    private function lineFromLegacyDetail($detail, int $sequence): array
    {
        $unit = $detail->kiosk_unit ?? null;
        $product = $unit ? $unit->product : null;
        $tax = $product ? $product->tax : null;
        $rate = $tax ? (float) $tax->rate : 0.0;

        $gross = round((float) $detail->price, 2);
        $base = $rate > 0 ? round($gross / (1 + ($rate / 100)), 2) : $gross;
        $taxAmount = round($gross - $base, 2);

        $line = [
            'sequence' => (string) $sequence,
            'description' => $this->describeProduct($product, $unit, $detail),
            'quantity' => $this->quantity(KioskFiscalSnapshotBuilder::DEFAULT_QUANTITY),
            'unit_code' => KioskFiscalSnapshotBuilder::DEFAULT_UNIT_MEASURE,
            'unit_price' => $this->money($base),
            'line_total' => $this->money($base),
        ];

        if ($rate > 0) {
            $line['taxable_amount'] = $this->money($base);
            $line['tax_amount'] = $this->money($taxAmount);
            $line['tax_percent'] = $this->percent($rate);
            $line['tax_scheme_code'] = self::TAX_CODE_IVA;
            $line['tax_scheme_name'] = self::TAX_NAME_IVA;
        }

        return $line;
    }

    private function quantity(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function describeProduct($product, $unit, $detail): string
    {
        if ($product && !empty($product->name)) {
            return (string) $product->name;
        }
        if ($unit && !empty($unit->code_complement)) {
            return 'Kiosk unit ' . $unit->code_complement;
        }
        return 'Kiosk item #' . (int) $detail->id;
    }

    private function normaliseTaxes(array $bucket): array
    {
        $out = [];
        foreach ($bucket as $entry) {
            $entry['taxable_amount'] = $this->money($entry['taxable_amount']);
            $entry['tax_amount'] = $this->money($entry['tax_amount']);
            $out[] = $entry;
        }
        return $out;
    }

    private function paymentBlock(KioskInvoice $invoice): array
    {
        $isCash = !$invoice->relationLoaded('payment_type')
            ? false
            : ($invoice->payment_type !== null && empty($invoice->payment_type->credit));

        return [
            'means_code' => $isCash ? '10' : '49',
            'terms_code' => empty($invoice->payment_type) || empty($invoice->payment_type->credit) ? '1' : '2',
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

    private function notesBlock(KioskInvoice $invoice): ?string
    {
        if (!empty($invoice->payment_code)) {
            return 'KioskInvoice payment_code=' . $invoice->payment_code;
        }
        return null;
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function percent(float $rate): string
    {
        return number_format($rate, 2, '.', '');
    }
}
