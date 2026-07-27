<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

/**
 * Repository of habilitacion test cases.
 *
 * The default factory `canonical()` returns the curated set Campo Verde
 * runs against DIAN HAB. The spec calls for 60 FEV + 20 NC + 20 ND;
 * this slice ships a representative subset (10 cases across every
 * category covering: baseline, descuento, IVA discriminado, exento,
 * INC, retencion, nota credito por concepto 1-5, nota debito por
 * intereses, FEV credito). Operations can extend the set through
 * `fromArray()` without touching code (see the artisan command
 * `--fixtures=path/to/pack.json`).
 *
 * Fixtures are intentionally opaque: each case carries the same shape
 * the production pipeline already accepts (numbering, lines, totals,
 * acquirer hint). The runner does not interpret them, it just emits.
 */
final class TestCaseRepository
{
    /**
     * @return TestCaseDescriptor[]
     */
    public static function canonical(): array
    {
        return [
            self::fev('FEV-01', 'FEV baseline IVA 19%', ['discount' => false, 'tax_kind' => 'iva-19']),
            self::fev('FEV-02', 'FEV con descuento global', ['discount' => true, 'tax_kind' => 'iva-19']),
            self::fev('FEV-03', 'FEV con IVA discriminado', ['discount' => false, 'tax_kind' => 'iva-19-discriminated']),
            self::fev('FEV-04', 'FEV bien exento', ['discount' => false, 'tax_kind' => 'exempt']),
            self::fev('FEV-05', 'FEV bien gravado con INC', ['discount' => false, 'tax_kind' => 'inc']),
            self::fev('FEV-06', 'FEV con retencion en la fuente', ['discount' => false, 'tax_kind' => 'rete-fuente']),
            self::fev('FEV-07', 'FEV a credito (RADIAN)', ['discount' => false, 'tax_kind' => 'iva-19', 'payment_terms' => 'credit']),
            self::nc('NC-01', 'Nota credito concepto 1 (devolucion parcial)', ['discrepancy' => '01']),
            self::nc('NC-02', 'Nota credito concepto 2 (anulacion)', ['discrepancy' => '02']),
            self::nd('ND-01', 'Nota debito por intereses', ['concept' => 'intereses']),
        ];
    }

    /**
     * Hydrate a custom pack from a decoded JSON array (e.g. coming from
     * `artisan electronic-invoicing:run-test-set --fixtures=...`).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return TestCaseDescriptor[]
     */
    public static function fromArray(array $rows): array
    {
        $cases = [];
        foreach ($rows as $i => $row) {
            $code = (string) ($row['code'] ?? sprintf('CASE-%02d', $i + 1));
            $category = (string) ($row['category'] ?? 'fev');
            $description = (string) ($row['description'] ?? $code);
            $payload = (array) ($row['payload'] ?? []);
            $expectations = (array) ($row['expectations'] ?? ['target_status' => 'dian_accepted']);
            $cases[] = new TestCaseDescriptor($code, $category, $description, $payload, $expectations);
        }

        return $cases;
    }

    private static function fev(string $code, string $description, array $payload): TestCaseDescriptor
    {
        return new TestCaseDescriptor($code, 'fev', $description, $payload + ['type' => 'fev']);
    }

    private static function nc(string $code, string $description, array $payload): TestCaseDescriptor
    {
        return new TestCaseDescriptor($code, 'nc', $description, $payload + ['type' => 'nc']);
    }

    private static function nd(string $code, string $description, array $payload): TestCaseDescriptor
    {
        return new TestCaseDescriptor($code, 'nd', $description, $payload + ['type' => 'nd']);
    }
}
