<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\TestCase;

class LegacyPtImportControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    public function test_admin_can_import_consistent_bundle(): void
    {
        $company = $this->seedCompany();
        $this->actingAsAdmin();

        $cufe = '0123456789abcdef';
        $response = $this->postJson('/api/electronic-invoicing/legacy-pt/import', [
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [[
                'legacy_pt_id' => 'PT-1',
                'document_type' => 'fev',
                'dian_number' => 'SETP990000001',
                'cufe_cude' => $cufe,
                'issue_date' => '2024-12-31',
                'total' => '119000.00',
                'currency_code' => 'COP',
                'xml_base64' => base64_encode($this->buildXmlWithUuid($cufe)),
            ]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('legacy_pt_import.status', 'completed')
            ->assertJsonPath('legacy_pt_import.total_documents', 1)
            ->assertJsonPath('legacy_pt_import.consistent_count', 1);
    }

    public function test_returns_422_when_payload_is_invalid(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/electronic-invoicing/legacy-pt/import', [
            'company_id' => 1,
            'documents' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('electronic_document_error.code', 'legacy_pt_import_payload_invalid');
    }

    public function test_returns_422_when_company_not_found(): void
    {
        $this->actingAsAdmin();

        $cufe = '0123456789abcdef';
        $response = $this->postJson('/api/electronic-invoicing/legacy-pt/import', [
            'company_id' => 99999,
            'source_pt_name' => 'Soenac',
            'documents' => [[
                'legacy_pt_id' => 'PT-1',
                'document_type' => 'fev',
                'dian_number' => 'SETP990000001',
                'cufe_cude' => $cufe,
                'issue_date' => '2024-12-31',
                'total' => '119000.00',
                'xml_base64' => base64_encode($this->buildXmlWithUuid($cufe)),
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('electronic_document_error.code', 'company_not_found');
    }

    public function test_endpoint_is_protected_by_admin_permission(): void
    {
        $company = $this->seedCompany();
        $user = $this->makeUser('viewer@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.list']);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/electronic-invoicing/legacy-pt/import', [
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [['legacy_pt_id' => 'PT-1']],
        ]);

        $response->assertStatus(401);
    }

    private function actingAsAdmin(): void
    {
        $user = $this->makeUser('admin@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.admin']);
        $this->actingAs($user, 'sanctum');
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => bcrypt('test1234'),
        ]);
    }

    private function seedCompany(): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'co',
            'email' => 'fiscal@cv.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
    }

    private function buildXmlWithUuid(string $uuid): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:UUID>{$uuid}</cbc:UUID>
</Invoice>
XML;
    }
}
