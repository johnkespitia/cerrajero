<?php

namespace App\Services\ElectronicInvoicing;

use App\Models\KioskInvoiceDetail;
use App\Models\KioskUnit;

/**
 * Captures the fiscal data of a kiosk sale line at the moment it is closed.
 *
 * The snapshot is intentionally derived from the **gross** price that the
 * UI sends so a subsequent edit to `KioskProduct.tax_id` or `Tax.rate`
 * cannot change the historical breakdown. The line is stored as:
 *
 *   base + tax_amount = line_total       (line_total == price ingresado)
 *   base * (rate/100) ≈ tax_amount       (rounded to 2 decimals)
 *
 * When a product carries no tax association, the line is treated as
 * tax-excluded: `tax_amount = 0`, `tax_code_dian = null`, `tax_rate = 0`.
 *
 * The builder NEVER mutates the database directly; callers persist the
 * payload via `KioskInvoiceDetail::create($payload + $snapshot)` or
 * `$detail->update($snapshot)` so transaction boundaries remain owned by
 * the controller.
 */
final class KioskFiscalSnapshotBuilder
{
    public const TAX_CODE_IVA = '01';
    public const TAX_NAME_IVA = 'IVA';
    public const DEFAULT_UNIT_MEASURE = 'NIU';
    public const DEFAULT_QUANTITY = 1.0;

    /**
     * Build the snapshot for a single sold KioskUnit.
     *
     * @param KioskUnit|null $unit  Loaded with `product.tax` when possible.
     * @param float|int|string $grossPrice  The total amount the customer paid for this line.
     * @return array<string, mixed>
     */
    public function buildForUnit($unit, $grossPrice): array
    {
        $product = $unit !== null ? $unit->product : null;
        $tax = $product !== null ? $product->tax : null;

        $gross = (float) $grossPrice;
        if ($gross < 0) {
            $gross = 0.0;
        }
        $rate = $tax !== null ? (float) $tax->rate : 0.0;

        if ($rate > 0) {
            $base = round($gross / (1 + ($rate / 100)), 2);
            $taxAmount = round($gross - $base, 2);
            $taxId = (int) $tax->id;
            $taxCode = self::TAX_CODE_IVA;
            $taxName = $tax->name !== null && $tax->name !== ''
                ? (string) $tax->name
                : self::TAX_NAME_IVA;
        } else {
            $base = $gross;
            $taxAmount = 0.0;
            $taxId = null;
            $taxCode = null;
            $taxName = null;
        }

        $extra = [
            'product_id' => $product !== null ? (int) $product->id : null,
            'product_name' => $product !== null ? (string) $product->name : null,
            'product_code' => $product !== null ? ($product->code ?? null) : null,
            'unit_code_complement' => $unit !== null ? ($unit->code_complement ?? null) : null,
            'sale_price' => $product !== null ? (float) $product->sale_price : null,
            'pricing' => [
                'gross' => $this->money($gross),
                'base' => $this->money($base),
                'tax_amount' => $this->money($taxAmount),
                'rate' => $this->rate($rate),
            ],
            'captured_at' => date(DATE_ATOM),
        ];

        return [
            'fiscal_tax_id' => $taxId,
            'fiscal_tax_code_dian' => $taxCode,
            'fiscal_tax_name' => $taxName,
            'fiscal_tax_rate' => $rate > 0 ? $this->rate($rate) : '0.0000',
            'fiscal_unit_measure_dian' => self::DEFAULT_UNIT_MEASURE,
            'fiscal_quantity' => $this->quantity(self::DEFAULT_QUANTITY),
            'fiscal_unit_price' => $this->money($base),
            'fiscal_base_amount' => $this->money($base),
            'fiscal_tax_amount' => $this->money($taxAmount),
            'fiscal_line_total' => $this->money($gross),
            'fiscal_snapshot' => $extra,
        ];
    }

    /**
     * Convenience wrapper that updates an existing detail row in place
     * (only the snapshot columns; other fields are preserved).
     */
    public function applyToDetail(KioskInvoiceDetail $detail, $unit, $grossPrice): void
    {
        $snapshot = $this->buildForUnit($unit, $grossPrice);
        $detail->fill($snapshot);
        $detail->save();
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function rate($value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function quantity($value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
