<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Models\KioskInvoiceDetail;
use App\Models\KioskProduct;
use App\Models\KioskUnit;
use App\Models\Tax;
use App\Services\ElectronicInvoicing\KioskFiscalSnapshotBuilder;
use Tests\TestCase;

class KioskFiscalSnapshotBuilderTest extends TestCase
{
    /** @var KioskFiscalSnapshotBuilder */
    private $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new KioskFiscalSnapshotBuilder();
    }

    public function test_builds_snapshot_for_taxable_unit_reverses_gross_into_base_and_tax(): void
    {
        $unit = $this->makeUnit('Premium room service', 100000, 19.0);
        $snapshot = $this->builder->buildForUnit($unit, 119000);

        $this->assertSame(KioskFiscalSnapshotBuilder::TAX_CODE_IVA, $snapshot['fiscal_tax_code_dian']);
        $this->assertSame('IVA-19', $snapshot['fiscal_tax_name']);
        $this->assertSame('19.0000', $snapshot['fiscal_tax_rate']);
        $this->assertSame(KioskFiscalSnapshotBuilder::DEFAULT_UNIT_MEASURE, $snapshot['fiscal_unit_measure_dian']);
        $this->assertSame('1.000', $snapshot['fiscal_quantity']);
        $this->assertSame('100000.00', $snapshot['fiscal_unit_price']);
        $this->assertSame('100000.00', $snapshot['fiscal_base_amount']);
        $this->assertSame('19000.00', $snapshot['fiscal_tax_amount']);
        $this->assertSame('119000.00', $snapshot['fiscal_line_total']);
        $this->assertIsArray($snapshot['fiscal_snapshot']);
        $this->assertSame('Premium room service', $snapshot['fiscal_snapshot']['product_name']);
        $this->assertSame('119000.00', $snapshot['fiscal_snapshot']['pricing']['gross']);
        $this->assertSame('100000.00', $snapshot['fiscal_snapshot']['pricing']['base']);
        $this->assertSame('19000.00', $snapshot['fiscal_snapshot']['pricing']['tax_amount']);
    }

    public function test_builds_snapshot_for_product_without_tax_marks_zero_tax_amount(): void
    {
        $unit = $this->makeUnit('Producto exento', 50000, null);
        $snapshot = $this->builder->buildForUnit($unit, 50000);

        $this->assertNull($snapshot['fiscal_tax_id']);
        $this->assertNull($snapshot['fiscal_tax_code_dian']);
        $this->assertNull($snapshot['fiscal_tax_name']);
        $this->assertSame('0.0000', $snapshot['fiscal_tax_rate']);
        $this->assertSame('50000.00', $snapshot['fiscal_base_amount']);
        $this->assertSame('0.00', $snapshot['fiscal_tax_amount']);
        $this->assertSame('50000.00', $snapshot['fiscal_line_total']);
    }

    public function test_snapshot_defaults_unit_measure_and_quantity_to_niu_and_one(): void
    {
        $unit = $this->makeUnit('Producto', 1000, 0.0);
        $snapshot = $this->builder->buildForUnit($unit, 1000);
        $this->assertSame('NIU', $snapshot['fiscal_unit_measure_dian']);
        $this->assertSame('1.000', $snapshot['fiscal_quantity']);
    }

    public function test_apply_to_detail_persists_snapshot_fields(): void
    {
        $detail = new KioskInvoiceDetail([
            'kiosk_invoices_id' => 1,
            'kiosk_units_id' => 1,
            'price' => 119000,
        ]);
        $unit = $this->makeUnit('Café', 100000, 19.0);

        // We do not need a real DB row here: assert the fillable fields are
        // copied onto the model so the controller's `save()` would persist them.
        $snapshot = $this->builder->buildForUnit($unit, 119000);
        $detail->fill($snapshot);

        $this->assertSame('19000.00', $detail->fiscal_tax_amount);
        $this->assertSame('100000.00', $detail->fiscal_base_amount);
        $this->assertSame('119000.00', $detail->fiscal_line_total);
        $this->assertTrue($detail->hasFiscalSnapshot());
    }

    public function test_negative_price_clamps_to_zero(): void
    {
        $unit = $this->makeUnit('Producto', 0, 19.0);
        $snapshot = $this->builder->buildForUnit($unit, -50);
        $this->assertSame('0.00', $snapshot['fiscal_line_total']);
        $this->assertSame('0.00', $snapshot['fiscal_base_amount']);
        $this->assertSame('0.00', $snapshot['fiscal_tax_amount']);
    }

    private function makeUnit(string $productName, float $salePrice, ?float $taxRate): KioskUnit
    {
        $product = new KioskProduct([
            'name' => $productName,
            'code' => 'CODE-1',
            'sale_price' => $salePrice,
        ]);
        $product->id = 11;
        if ($taxRate !== null) {
            $tax = new Tax(['name' => 'IVA-' . (int) $taxRate, 'rate' => $taxRate]);
            $tax->id = 5;
            $product->setRelation('tax', $tax);
        } else {
            $product->setRelation('tax', null);
        }
        $unit = new KioskUnit([
            'code_complement' => 'U-1',
            'price' => $salePrice,
        ]);
        $unit->id = 42;
        $unit->setRelation('product', $product);
        return $unit;
    }
}
