<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Http\Controllers\KioskInvoiceController;
use App\Infrastructure\ElectronicInvoicing\Secrets\ArraySecretManager;
use App\Models\CompanyFiscalProfile;
use App\Models\Customer;
use App\Models\DianResolution;
use App\Models\DianSoftwareCredential;
use App\Models\ElectronicDocument;
use App\Models\KioskCategory;
use App\Models\KioskInvoice;
use App\Models\KioskInvoiceDetail;
use App\Models\KioskProduct;
use App\Models\KioskUnit;
use App\Models\PaymentType;
use App\Models\Tax;
use App\Models\User;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionInvalidPayloadException;
use App\Services\KioskOtpService;
use App\Services\ElectronicInvoicing\KioskInvoiceEmissionService;
use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class KioskInvoiceEmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['electronic-invoicing.enabled' => true]);
        config(['electronic-invoicing.environment' => FiscalEnvironment::HABILITACION]);
        config(['electronic-invoicing.secrets' => ['hab/pin' => 'TEST-PIN-HAB']]);

        $this->app->instance(SecretManagerInterface::class, new ArraySecretManager([
            'hab/pin' => 'TEST-PIN-HAB',
        ]));
    }

    public function test_store_creates_dee_pos_when_electronic_invoice_flag_is_false(): void
    {
        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        $this->seedFiscalConfiguration();

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'CASH-001',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => false,
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayHasKey('electronic_document', $body);
        $doc = $body['electronic_document'];
        $this->assertSame(DocumentType::DEE_POS, $doc['document_type']);
        $this->assertSame('ubl_built', $doc['status']);
        $this->assertSame('POS1', $doc['dian_number']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $doc['cufe_cude']);
        $this->assertNotNull($doc['qr_url']);
        $this->assertArrayNotHasKey('electronic_document_error', $body);

        $invoice = KioskInvoice::with('details')->latest('id')->first();
        $this->assertNotNull($invoice->electronic_document_id);
        $this->assertNull($invoice->acquirer_id);
        $this->assertSame($doc['id'], $invoice->electronic_document_id);
    }

    public function test_store_creates_fev_when_electronic_invoice_flag_true_with_valid_acquirer(): void
    {
        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        $this->seedFiscalConfiguration();

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'FE-100',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => true,
            'acquirer' => [
                'document_type' => '31',
                'document_number' => '800111222',
                'dv' => 3,
                'legal_name' => 'Cliente B2B SAS',
                'country_code' => 'CO',
                'address_line' => 'Cra 50',
                'email' => 'b2b@cliente.local',
                'tax_regime_code' => '48',
            ],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayHasKey('electronic_document', $body);
        $doc = $body['electronic_document'];
        $this->assertSame(DocumentType::FEV, $doc['document_type']);
        $this->assertSame('ubl_built', $doc['status']);
        $this->assertSame('SETP990000001', $doc['dian_number']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $doc['cufe_cude']);

        $document = ElectronicDocument::find($doc['id']);
        $this->assertNotNull($document->acquirer_id);
        $this->assertNotNull($document->cufe_cude);

        $invoice = KioskInvoice::latest('id')->first();
        $this->assertSame($document->acquirer_id, $invoice->acquirer_id);
        $this->assertSame($document->id, $invoice->electronic_document_id);
    }

    public function test_store_rejects_fev_without_acquirer(): void
    {
        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        $this->seedFiscalConfiguration();

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'FE-101',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => true,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayHasKey('electronic_document_error', $body);
        $this->assertSame(
            KioskEmissionInvalidPayloadException::CODE_MISSING_ACQUIRER,
            $body['electronic_document_error']['code']
        );

        $this->assertSame(0, KioskInvoice::count());
        $this->assertSame(0, ElectronicDocument::count());
    }

    public function test_store_preserves_legacy_behaviour_when_electronic_invoicing_disabled(): void
    {
        config(['electronic-invoicing.enabled' => false]);

        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        // No fiscal seed required: feature flag must be honoured before any emission attempt.

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'CASH-002',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => true, // even with the flag, EI is disabled
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayNotHasKey('electronic_document', $body);
        $this->assertArrayNotHasKey('electronic_document_error', $body);

        $this->assertSame(0, ElectronicDocument::count());
    }

    public function test_store_does_not_break_caja_when_fiscal_configuration_is_missing(): void
    {
        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        // NO fiscal seed: company, resolution, credential missing on purpose.

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'CASH-003',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => false,
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayNotHasKey('electronic_document', $body);
        $this->assertArrayHasKey('electronic_document_error', $body);
        $this->assertSame('fiscal_profile_missing', $body['electronic_document_error']['code']);
        $this->assertNotEmpty($body['electronic_document_error']['message']);

        $this->assertSame(1, KioskInvoice::count());
        $this->assertSame(0, ElectronicDocument::count());
    }

    public function test_store_persists_fiscal_snapshot_on_each_invoice_detail(): void
    {
        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        $this->seedFiscalConfiguration();

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'SNAP-001',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => false,
        ]);

        $this->assertSame(201, $response->getStatusCode());

        $detail = KioskInvoiceDetail::latest('id')->first();
        $this->assertNotNull($detail, 'Expected a KioskInvoiceDetail to exist.');
        $this->assertTrue($detail->hasFiscalSnapshot(), 'Detail should carry the fiscal snapshot.');
        $this->assertSame('01', $detail->fiscal_tax_code_dian);
        $this->assertSame('19.0000', (string) $detail->fiscal_tax_rate);
        $this->assertSame('NIU', $detail->fiscal_unit_measure_dian);
        $this->assertSame('1.000', (string) $detail->fiscal_quantity);
        $this->assertSame('100000.00', (string) $detail->fiscal_base_amount);
        $this->assertSame('19000.00', (string) $detail->fiscal_tax_amount);
        $this->assertSame('119000.00', (string) $detail->fiscal_line_total);
        $this->assertIsArray($detail->fiscal_snapshot);
        $this->assertSame('Souvenir Campo Verde', $detail->fiscal_snapshot['product_name']);
    }

    public function test_emission_uses_persisted_snapshot_even_when_product_tax_mutates_afterwards(): void
    {
        [$user, $customer, $paymentType, $unit] = $this->seedKioskCatalog();
        $this->seedFiscalConfiguration();

        $response = $this->callStore($user, [
            'customer_id' => $customer->id,
            'payment_code' => 'SNAP-002',
            'payment_type_id' => $paymentType->id,
            'units' => [['kiosk_units_id' => $unit->id, 'price' => 119000]],
            'electronic_invoice' => false,
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $firstDoc = $response->getData(true)['electronic_document'];

        // Simulate an admin lowering the IVA rate AFTER the sale closed.
        Tax::where('name', 'IVA-19')->update(['rate' => 5.00]);

        // Re-emit by re-reading the invoice. The snapshot must win.
        $invoice = KioskInvoice::with('details.kiosk_unit.product.tax', 'payment_type')->latest('id')->first();
        $builder = new \App\Services\ElectronicInvoicing\KioskEmissionContextBuilder();
        $context = $builder->buildFromKioskInvoice(
            $invoice,
            CompanyFiscalProfile::query()->where('environment', FiscalEnvironment::HABILITACION)->first(),
            DianResolution::query()
                ->where('environment', FiscalEnvironment::HABILITACION)
                ->where('document_type', DocumentType::DEE_POS)
                ->first(),
            DocumentType::DEE_POS,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'POS', 'sequence' => 1, 'number' => 'POS1', 'resolution_id' => 1],
            null,
            ['pin' => 'TEST-PIN-HAB', 'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef']
        );

        $this->assertSame('19000.00', $context['lines'][0]['tax_amount']);
        $this->assertSame('100000.00', $context['lines'][0]['taxable_amount']);
        $this->assertSame('19.00', $context['lines'][0]['tax_percent']);
        $this->assertNotEmpty($firstDoc['cufe_cude']);
    }

    private function callStore(User $user, array $payload)
    {
        $request = Request::create('/api/kiosk/invoice', 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        $this->actingAs($user);

        $controller = $this->app->make(KioskInvoiceController::class);
        return $controller->store($request);
    }

    private function seedKioskCatalog(): array
    {
        $user = User::create([
            'name' => 'Cajera Test',
            'email' => 'cajera@test.local',
            'password' => bcrypt('test1234'),
        ]);

        $customer = Customer::create([
            'dni' => '1010101010',
            'name' => 'Final',
            'last_name' => 'Consumer',
            'email' => 'final@consumer.local',
            'phone_number' => '3000000000',
            'active' => true,
            'customer_type' => 'person',
        ]);

        $paymentType = PaymentType::firstOrCreate(
            ['name' => 'Efectivo'],
            [
                'active' => true,
                'credit' => false,
                'calculator' => false,
            ]
        );

        $tax = Tax::firstOrCreate(['name' => 'IVA-19'], ['rate' => 19.00]);
        $category = KioskCategory::firstOrCreate(['name' => 'Souvenir'], ['active' => true]);
        $product = KioskProduct::create([
            'name' => 'Souvenir Campo Verde',
            'code' => 'SOUV-01',
            'active' => true,
            'category_id' => $category->id,
            'sale_price' => 119000,
            'tax_id' => $tax->id,
        ]);
        $unit = KioskUnit::create([
            'code_complement' => 'U-001',
            'price' => 119000,
            'active' => true,
            'product_id' => $product->id,
            'sold' => false,
        ]);

        return [$user, $customer, $paymentType, $unit];
    }

    private function seedFiscalConfiguration(): array
    {
        $company = CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@campoverde.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);

        $resolutionFev = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000001,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
            'current_number' => 0,
            'active' => true,
        ]);

        $resolutionPos = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::DEE_POS,
            'prefix' => 'POS',
            'resolution_number' => '18760000777',
            'resolution_date' => '2026-01-01',
            'from_number' => 1,
            'to_number' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'technical_key' => null,
            'current_number' => 0,
            'active' => true,
        ]);

        DianSoftwareCredential::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'software_pin_secret_ref' => 'ref:hab/pin',
            'test_set_id' => '00000000-1111-2222-3333-444444444444',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        ]);

        return [$company, $resolutionFev, $resolutionPos];
    }
}
