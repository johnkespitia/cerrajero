<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\TestCase;

class DocumentLookupControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['electronic-invoicing.enabled' => true]);
        config(['electronic-invoicing.environment' => FiscalEnvironment::HABILITACION]);
    }

    public function test_list_returns_documents_filtered_by_environment(): void
    {
        $company = $this->seedCompany();
        $fev = $this->seedDocument($company, [
            'document_type' => DocumentType::FEV,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'SETP990000001',
            'environment' => FiscalEnvironment::HABILITACION,
        ]);
        $this->seedDocument($company, [
            'document_type' => DocumentType::DEE_POS,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'POS9000001',
            'environment' => FiscalEnvironment::PRODUCTION,
        ]);

        $this->actingAsFiscal();

        $response = $this->getJson('/api/electronic-invoicing/documents?environment=habilitacion');

        $response->assertStatus(200)
            ->assertJsonPath('environment', 'habilitacion')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('documents.0.id', $fev->id)
            ->assertJsonPath('documents.0.document_type', DocumentType::FEV)
            ->assertJsonPath('documents.0.dian_number', 'SETP990000001');
    }

    public function test_list_filters_by_document_type_and_status(): void
    {
        $company = $this->seedCompany();
        $this->seedDocument($company, [
            'document_type' => DocumentType::FEV,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'SETP1',
        ]);
        $nc = $this->seedDocument($company, [
            'document_type' => DocumentType::NC,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'NCR1',
        ]);

        $this->actingAsFiscal();

        $response = $this->getJson('/api/electronic-invoicing/documents?document_type=nc');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('documents.0.id', $nc->id)
            ->assertJsonPath('documents.0.document_type', DocumentType::NC);
    }

    public function test_list_does_not_expose_xml_paths(): void
    {
        $company = $this->seedCompany();
        $doc = $this->seedDocument($company, [
            'document_type' => DocumentType::FEV,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'SETP-EXP',
            'xml_unsigned_path' => 'memory://fiscal/secret-unsigned.xml',
            'xml_signed_path' => 'memory://fiscal/secret-signed.xml',
        ]);

        $this->actingAsFiscal();

        $response = $this->getJson('/api/electronic-invoicing/documents');

        $response->assertStatus(200);
        $json = $response->json();
        $payload = json_encode($json);
        $this->assertStringNotContainsString('memory://fiscal/secret-unsigned.xml', $payload);
        $this->assertStringNotContainsString('memory://fiscal/secret-signed.xml', $payload);
        $this->assertSame(true, $json['documents'][0]['has_unsigned_xml']);
        $this->assertSame(true, $json['documents'][0]['has_signed_xml']);
        $this->assertSame($doc->id, $json['documents'][0]['id']);
    }

    public function test_list_rejects_unknown_document_type_filter(): void
    {
        $this->seedCompany();
        $this->actingAsFiscal();

        $response = $this->getJson('/api/electronic-invoicing/documents?document_type=payroll');
        $response->assertStatus(422)
            ->assertJsonPath('errors.document_type.0', 'Unknown document type "payroll".');
    }

    public function test_show_returns_document_with_parent_reference(): void
    {
        $company = $this->seedCompany();
        $parent = $this->seedDocument($company, [
            'document_type' => DocumentType::FEV,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'SETP990000001',
            'cufe_cude' => str_repeat('a', 96),
        ]);
        $nc = $this->seedDocument($company, [
            'document_type' => DocumentType::NC,
            'status' => DocumentStatus::UBL_BUILT,
            'dian_number' => 'NCR1',
            'references_document_id' => $parent->id,
            'source_type' => 'credit_note',
            'source_id' => $parent->id,
        ]);

        $this->actingAsFiscal();

        $response = $this->getJson("/api/electronic-invoicing/documents/{$nc->id}");

        $response->assertStatus(200)
            ->assertJsonPath('document.id', $nc->id)
            ->assertJsonPath('document.references_document_id', $parent->id)
            ->assertJsonPath('document.reference.id', $parent->id)
            ->assertJsonPath('document.reference.dian_number', 'SETP990000001');
    }

    public function test_show_returns_404_when_not_found(): void
    {
        $this->actingAsFiscal();
        $response = $this->getJson('/api/electronic-invoicing/documents/9999');
        $response->assertStatus(404);
    }

    private function actingAsFiscal(?array $permissions = null): void
    {
        $user = User::create([
            'name' => 'Fiscal',
            'email' => 'fiscal-dash@test.local',
            'password' => bcrypt('test1234'),
        ]);
        $this->grantElectronicInvoicingPermissions($user, $permissions);
        $this->actingAs($user, 'sanctum');
    }

    private function seedCompany(): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
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
    }

    private function seedDocument(CompanyFiscalProfile $company, array $overrides = []): ElectronicDocument
    {
        $resolution = $this->seedResolution($company, (string) ($overrides['document_type'] ?? DocumentType::FEV));
        $defaults = [
            'company_id' => $company->id,
            'resolution_id' => $resolution->id,
            'document_type' => DocumentType::FEV,
            'reference_code' => 'ref-' . uniqid('', true),
            'dian_number' => 'SETP0',
            'cufe_cude' => null,
            'status' => DocumentStatus::DRAFT,
            'environment' => FiscalEnvironment::HABILITACION,
            'subtotal' => '100000.00',
            'total_taxes' => '19000.00',
            'total' => '119000.00',
            'currency_code' => 'COP',
            'issue_date' => Carbon::create(2026, 3, 26, 10, 30, 0),
            'source_type' => 'kiosk_invoice',
            'source_id' => 1,
        ];
        return ElectronicDocument::create(array_merge($defaults, $overrides));
    }

    private function seedResolution(CompanyFiscalProfile $company, string $documentType): DianResolution
    {
        return DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => $documentType,
            'prefix' => 'SETP',
            'resolution_number' => '18760000000' . random_int(1, 9999),
            'resolution_date' => '2026-01-01',
            'from_number' => 1,
            'to_number' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'technical_key' => null,
            'current_number' => 0,
            'active' => true,
        ]);
    }
}
