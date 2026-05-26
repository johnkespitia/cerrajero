<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\KioskInvoice;
use App\Models\KioskInvoiceDetail;
use App\Models\KioskProduct;
use App\Models\KioskUnit;
use App\Models\PaymentType;
use App\Models\Tax;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionInvalidPayloadException;
use App\Services\ElectronicInvoicing\KioskEmissionContextBuilder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class KioskEmissionContextBuilderTest extends TestCase
{
    /** @var KioskEmissionContextBuilder */
    private $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new KioskEmissionContextBuilder();
    }

    public function test_builds_canonical_context_from_kiosk_invoice_with_iva_19(): void
    {
        $invoice = $this->makeKioskInvoice([
            $this->makeDetailWithTax('Premium room service', 119000, 19.0),
            $this->makeDetailWithTax('Mini-bar snack', 23800, 19.0),
        ], $this->cashPaymentType());

        $context = $this->builder->buildFromKioskInvoice(
            $invoice,
            $this->company(),
            $this->resolution(),
            DocumentType::DEE_POS,
            FiscalEnvironment::HABILITACION,
            [
                'prefix' => 'POS',
                'sequence' => 1,
                'number' => 'POS1',
                'resolution_id' => 7,
            ],
            null,
            ['pin' => 'PIN-TEST', 'software_id' => 'soft-uuid']
        );

        $this->assertSame(DocumentType::DEE_POS, $context['document_type']);
        $this->assertSame(FiscalEnvironment::HABILITACION, $context['environment']);
        $this->assertCount(2, $context['lines']);

        $first = $context['lines'][0];
        $this->assertSame('Premium room service', $first['description']);
        $this->assertSame('100000.00', $first['unit_price']);
        $this->assertSame('100000.00', $first['line_total']);
        $this->assertSame('19000.00', $first['tax_amount']);
        $this->assertSame('100000.00', $first['taxable_amount']);
        $this->assertSame('19.00', $first['tax_percent']);
        $this->assertSame('01', $first['tax_scheme_code']);

        $this->assertSame('120000.00', $context['totals']['line_extension_amount']);
        $this->assertSame('120000.00', $context['totals']['tax_exclusive_amount']);
        $this->assertSame('142800.00', $context['totals']['tax_inclusive_amount']);
        $this->assertSame('142800.00', $context['totals']['payable_amount']);

        $this->assertCount(1, $context['taxes']);
        $this->assertSame('01', $context['taxes'][0]['code']);
        $this->assertSame('22800.00', $context['taxes'][0]['tax_amount']);
        $this->assertSame('120000.00', $context['taxes'][0]['taxable_amount']);

        $this->assertSame('10', $context['payment']['means_code']);
        $this->assertSame('1', $context['payment']['terms_code']);

        $this->assertSame('kiosk_invoice', $context['source_meta']['source_type']);
        $this->assertSame(55, $context['source_meta']['source_id']);

        $this->assertSame(['pin' => 'PIN-TEST', 'software_id' => 'soft-uuid'], $context['cufe_signing']);
        $this->assertSame(['software_id' => 'soft-uuid', 'pin' => 'PIN-TEST'], $context['software_credential']);
    }

    public function test_builds_fev_context_with_acquirer(): void
    {
        $invoice = $this->makeKioskInvoice([
            $this->makeDetailWithTax('Habitacion noche 101', 200000, 19.0),
        ], $this->cashPaymentType(), [
            'electronic_invoice' => true,
        ]);

        $acquirer = new ElectronicDocumentAcquirer([
            'document_type' => '31',
            'document_number' => '800111222',
            'dv' => 3,
            'legal_name' => 'Cliente B2B SAS',
            'country_code' => 'CO',
        ]);
        $acquirer->id = 9;

        $context = $this->builder->buildFromKioskInvoice(
            $invoice,
            $this->company(),
            $this->resolution(['technical_key' => 'fc8eac422eba16e22ffd8c6f']),
            DocumentType::FEV,
            FiscalEnvironment::HABILITACION,
            [
                'prefix' => 'SETP',
                'sequence' => 990000001,
                'number' => 'SETP990000001',
                'resolution_id' => 7,
            ],
            $acquirer,
            ['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f']
        );

        $this->assertSame(DocumentType::FEV, $context['document_type']);
        $this->assertSame($acquirer, $context['acquirer']);
        $this->assertSame(9, $context['acquirer_id']);
        $this->assertSame(['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f'], $context['cufe_signing']);
        $this->assertNull($context['software_credential']);
    }

    public function test_product_without_tax_yields_zero_tax_amount(): void
    {
        $invoice = $this->makeKioskInvoice([
            $this->makeDetailWithoutTax('Producto exento', 50000),
        ], $this->cashPaymentType());

        $context = $this->builder->buildFromKioskInvoice(
            $invoice,
            $this->company(),
            $this->resolution(),
            DocumentType::DEE_POS,
            FiscalEnvironment::HABILITACION,
            [
                'prefix' => 'POS',
                'sequence' => 1,
                'number' => 'POS1',
                'resolution_id' => 7,
            ],
            null,
            ['pin' => 'PIN-TEST', 'software_id' => 'soft-uuid']
        );

        $this->assertSame('50000.00', $context['lines'][0]['line_total']);
        $this->assertArrayNotHasKey('tax_amount', $context['lines'][0]);
        $this->assertSame([], $context['taxes']);
        $this->assertSame('50000.00', $context['totals']['payable_amount']);
    }

    public function test_uses_persisted_snapshot_even_when_product_tax_changes_afterwards(): void
    {
        // Captura: el detalle se vendió con IVA 19% sobre $119.000 → base 100.000.
        $detail = $this->makeDetailWithSnapshot('Café especial', [
            'fiscal_tax_id' => 5,
            'fiscal_tax_code_dian' => '01',
            'fiscal_tax_name' => 'IVA-19',
            'fiscal_tax_rate' => '19.0000',
            'fiscal_unit_measure_dian' => 'NIU',
            'fiscal_quantity' => '1.000',
            'fiscal_unit_price' => '100000.00',
            'fiscal_base_amount' => '100000.00',
            'fiscal_tax_amount' => '19000.00',
            'fiscal_line_total' => '119000.00',
            'fiscal_snapshot' => ['product_name' => 'Café especial'],
            'price' => 119000,
        ], $mutatedTaxRate = 5.0);

        $invoice = $this->makeKioskInvoice([$detail], $this->cashPaymentType());

        $context = $this->builder->buildFromKioskInvoice(
            $invoice,
            $this->company(),
            $this->resolution(),
            DocumentType::DEE_POS,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'POS', 'sequence' => 1, 'number' => 'POS1', 'resolution_id' => 7],
            null,
            ['pin' => 'PIN-TEST', 'software_id' => 'soft-uuid']
        );

        $line = $context['lines'][0];
        // El builder DEBE respetar el snapshot, NO el rate mutado a 5% del producto.
        $this->assertSame('100000.00', $line['taxable_amount']);
        $this->assertSame('19000.00', $line['tax_amount']);
        $this->assertSame('19.00', $line['tax_percent']);
        $this->assertSame('01', $line['tax_scheme_code']);
        $this->assertSame('IVA-19', $line['tax_scheme_name']);
        $this->assertSame('NIU', $line['unit_code']);
        $this->assertSame('1.000', $line['quantity']);
        $this->assertSame('Café especial', $line['description']);

        // El total facturable lo manda el snapshot, no el producto vigente.
        $this->assertSame('100000.00', $context['totals']['line_extension_amount']);
        $this->assertSame('119000.00', $context['totals']['payable_amount']);
    }

    public function test_fallback_legacy_path_runs_when_detail_has_no_snapshot(): void
    {
        $detail = $this->makeDetailWithTax('Tiquete legado', 119000, 19.0);
        $invoice = $this->makeKioskInvoice([$detail], $this->cashPaymentType());

        $context = $this->builder->buildFromKioskInvoice(
            $invoice,
            $this->company(),
            $this->resolution(),
            DocumentType::DEE_POS,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'POS', 'sequence' => 1, 'number' => 'POS1', 'resolution_id' => 7],
            null,
            ['pin' => 'PIN-TEST', 'software_id' => 'soft-uuid']
        );

        $this->assertSame('100000.00', $context['lines'][0]['taxable_amount']);
        $this->assertSame('19000.00', $context['lines'][0]['tax_amount']);
        $this->assertSame('19.00', $context['lines'][0]['tax_percent']);
    }

    public function test_empty_details_raises_invalid_payload(): void
    {
        $invoice = $this->makeKioskInvoice([], $this->cashPaymentType());

        $this->expectException(KioskEmissionInvalidPayloadException::class);
        $this->builder->buildFromKioskInvoice(
            $invoice,
            $this->company(),
            $this->resolution(),
            DocumentType::DEE_POS,
            FiscalEnvironment::HABILITACION,
            [
                'prefix' => 'POS',
                'sequence' => 1,
                'number' => 'POS1',
                'resolution_id' => 7,
            ],
            null,
            ['pin' => 'PIN-TEST', 'software_id' => 'soft-uuid']
        );
    }

    private function makeKioskInvoice(array $details, PaymentType $paymentType, array $overrides = []): KioskInvoice
    {
        $invoice = new KioskInvoice(array_merge([
            'customer_id' => 1,
            'reservation_id' => null,
            'payed' => true,
            'payment_code' => 'CASH-1',
            'payment_type_id' => 1,
            'electronic_invoice' => false,
        ], $overrides));
        $invoice->id = 55;
        $invoice->setRelation('details', new Collection($details));
        $invoice->setRelation('payment_type', $paymentType);
        $invoice->created_at = Carbon::create(2026, 3, 26, 10, 30, 0);
        $invoice->updated_at = Carbon::create(2026, 3, 26, 10, 30, 0);
        return $invoice;
    }

    private function makeDetailWithTax(string $productName, float $price, float $taxRate): KioskInvoiceDetail
    {
        $detail = new KioskInvoiceDetail([
            'kiosk_invoices_id' => 55,
            'kiosk_units_id' => 1,
            'price' => $price,
        ]);
        $detail->id = (int) round($price);

        $tax = new Tax(['name' => 'IVA-19', 'rate' => $taxRate]);
        $product = new KioskProduct(['name' => $productName, 'sale_price' => $price]);
        $product->setRelation('tax', $tax);

        $unit = new KioskUnit(['code_complement' => 'U-' . $detail->id, 'price' => $price]);
        $unit->setRelation('product', $product);

        $detail->setRelation('kiosk_unit', $unit);
        return $detail;
    }

    /**
     * Build a detail that already carries a persisted fiscal snapshot.
     * The optional $mutatedTaxRate simulates an upstream Tax row that was
     * changed *after* the sale closed — the builder must ignore it.
     */
    private function makeDetailWithSnapshot(string $productName, array $snapshot, float $mutatedTaxRate = 0.0): KioskInvoiceDetail
    {
        $detail = new KioskInvoiceDetail(array_merge([
            'kiosk_invoices_id' => 55,
            'kiosk_units_id' => 1,
            'price' => 0,
        ], $snapshot));
        $detail->id = 1001;

        $tax = new Tax(['name' => 'IVA-mutado', 'rate' => $mutatedTaxRate]);
        $tax->id = 5;
        $product = new KioskProduct(['name' => $productName . ' (mutado)', 'sale_price' => 0]);
        $product->id = 11;
        $product->setRelation('tax', $tax);
        $unit = new KioskUnit(['code_complement' => 'U-snap', 'price' => 0]);
        $unit->id = 42;
        $unit->setRelation('product', $product);
        $detail->setRelation('kiosk_unit', $unit);
        return $detail;
    }

    private function makeDetailWithoutTax(string $productName, float $price): KioskInvoiceDetail
    {
        $detail = new KioskInvoiceDetail([
            'kiosk_invoices_id' => 55,
            'kiosk_units_id' => 1,
            'price' => $price,
        ]);
        $detail->id = (int) round($price);

        $product = new KioskProduct(['name' => $productName, 'sale_price' => $price]);
        $product->setRelation('tax', null);

        $unit = new KioskUnit(['code_complement' => 'U-' . $detail->id, 'price' => $price]);
        $unit->setRelation('product', $product);

        $detail->setRelation('kiosk_unit', $unit);
        return $detail;
    }

    private function cashPaymentType(): PaymentType
    {
        $payment = new PaymentType(['name' => 'Efectivo', 'credit' => false]);
        $payment->id = 1;
        return $payment;
    }

    private function company(): CompanyFiscalProfile
    {
        $company = new CompanyFiscalProfile([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
        $company->id = 1;
        return $company;
    }

    private function resolution(array $overrides = []): DianResolution
    {
        $resolution = new DianResolution(array_merge([
            'company_id' => 1,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'POS',
            'from_number' => 1,
            'to_number' => 10000,
            'current_number' => 0,
            'active' => true,
        ], $overrides));
        $resolution->id = 7;
        return $resolution;
    }
}
