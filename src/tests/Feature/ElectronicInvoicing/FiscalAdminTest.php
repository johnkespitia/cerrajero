<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use App\Infrastructure\ElectronicInvoicing\Secrets\ArraySecretManager;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\DianSoftwareCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['electronic-invoicing.environment' => FiscalEnvironment::HABILITACION]);
        config(['electronic-invoicing.enabled' => true]);
        config(['electronic-invoicing.allow_production_writes' => false]);
        $this->app->instance(SecretManagerInterface::class, new ArraySecretManager([
            'hab/pin' => 'TEST-PIN-HAB',
        ]));

        $user = User::create([
            'name' => 'Fiscal Admin',
            'email' => 'fiscal-admin@test.local',
            'password' => bcrypt('test1234'),
        ]);
        $this->actingAs($user, 'sanctum');
    }

    public function test_company_profile_show_returns_null_when_not_configured(): void
    {
        $response = $this->getJson('/api/electronic-invoicing/company-profile');
        $response->assertStatus(200)
            ->assertJsonPath('environment', FiscalEnvironment::HABILITACION)
            ->assertJsonPath('profile', null);
    }

    public function test_company_profile_update_creates_and_returns_persisted_profile(): void
    {
        $payload = [
            'environment' => FiscalEnvironment::HABILITACION,
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'co',
            'email' => 'fiscal@campoverde.local',
            'active' => true,
        ];
        $response = $this->putJson('/api/electronic-invoicing/company-profile', $payload);
        $response->assertStatus(200)
            ->assertJsonPath('profile.legal_name', 'Campo Verde S.A.S.')
            ->assertJsonPath('profile.country_code', 'CO')
            ->assertJsonPath('profile.environment', FiscalEnvironment::HABILITACION)
            ->assertJsonPath('profile.active', true);

        $this->assertDatabaseHas('company_fiscal_profiles', [
            'nit' => '900123456',
            'environment' => FiscalEnvironment::HABILITACION,
        ]);
    }

    public function test_company_profile_update_rejects_production_environment_by_default(): void
    {
        $payload = $this->companyPayload([
            'environment' => FiscalEnvironment::PRODUCTION,
        ]);
        $response = $this->putJson('/api/electronic-invoicing/company-profile', $payload);
        $response->assertStatus(400)
            ->assertJsonStructure(['errors' => ['environment']]);
    }

    public function test_software_credential_update_requires_secret_reference_prefix(): void
    {
        $company = $this->seedCompany();
        $payload = [
            'environment' => FiscalEnvironment::HABILITACION,
            'company_id' => $company->id,
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'software_pin_secret_ref' => 'ABC123PINRAW',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        ];
        $response = $this->putJson('/api/electronic-invoicing/software-credentials', $payload);
        $response->assertStatus(400)
            ->assertJsonStructure(['errors' => ['software_pin_secret_ref']]);
    }

    public function test_software_credential_update_persists_reference_but_hides_value(): void
    {
        $company = $this->seedCompany();
        $payload = [
            'environment' => FiscalEnvironment::HABILITACION,
            'company_id' => $company->id,
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'software_pin_secret_ref' => 'ref:hab/pin',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        ];
        $response = $this->putJson('/api/electronic-invoicing/software-credentials', $payload);
        $response->assertStatus(200)
            ->assertJsonPath('credential.software_id', 'a4b3c2d1-e5f6-7890-1234-567890abcdef')
            ->assertJsonPath('pin_reference_configured', true);
        $body = $response->json();
        $this->assertArrayNotHasKey('software_pin_secret_ref', $body['credential']);
    }

    public function test_resolution_store_validates_fev_requires_technical_key(): void
    {
        $company = $this->seedCompany();
        $payload = [
            'environment' => FiscalEnvironment::HABILITACION,
            'company_id' => $company->id,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000001,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'active' => true,
        ];
        $response = $this->postJson('/api/electronic-invoicing/resolutions', $payload);
        $response->assertStatus(400)
            ->assertJsonStructure(['errors' => ['technical_key']]);
    }

    public function test_resolution_store_creates_dee_pos_resolution_without_technical_key(): void
    {
        $company = $this->seedCompany();
        $payload = [
            'environment' => FiscalEnvironment::HABILITACION,
            'company_id' => $company->id,
            'document_type' => DocumentType::DEE_POS,
            'prefix' => 'POS',
            'resolution_number' => '18760000777',
            'resolution_date' => '2026-01-01',
            'from_number' => 1,
            'to_number' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'active' => true,
        ];
        $response = $this->postJson('/api/electronic-invoicing/resolutions', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('resolution.document_type', DocumentType::DEE_POS)
            ->assertJsonPath('resolution.environment', FiscalEnvironment::HABILITACION)
            ->assertJsonPath('resolution.active', true);

        $this->assertDatabaseHas('dian_resolutions', [
            'prefix' => 'POS',
            'document_type' => DocumentType::DEE_POS,
        ]);
    }

    public function test_resolution_store_rejects_inverted_range(): void
    {
        $company = $this->seedCompany();
        $payload = [
            'environment' => FiscalEnvironment::HABILITACION,
            'company_id' => $company->id,
            'document_type' => DocumentType::DEE_POS,
            'prefix' => 'POS',
            'resolution_number' => '18760000999',
            'resolution_date' => '2026-01-01',
            'from_number' => 100,
            'to_number' => 50,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'active' => true,
        ];
        $response = $this->postJson('/api/electronic-invoicing/resolutions', $payload);
        $response->assertStatus(400)
            ->assertJsonStructure(['errors' => ['to_number']]);
    }

    public function test_resolution_destroy_refuses_to_drop_used_resolution(): void
    {
        $company = $this->seedCompany();
        $resolution = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::DEE_POS,
            'prefix' => 'POS',
            'resolution_number' => '18760000888',
            'resolution_date' => '2026-01-01',
            'from_number' => 1,
            'to_number' => 100,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'current_number' => 5,
            'active' => true,
        ]);
        $response = $this->deleteJson(
            '/api/electronic-invoicing/resolutions/' . $resolution->id,
            ['environment' => FiscalEnvironment::HABILITACION]
        );
        $response->assertStatus(400)
            ->assertJsonStructure(['errors' => ['resolution']]);
    }

    public function test_healthcheck_reports_missing_configuration(): void
    {
        $response = $this->getJson('/api/electronic-invoicing/healthcheck');
        $response->assertStatus(200)
            ->assertJsonPath('company_profile.configured', false)
            ->assertJsonPath('software_credential.configured', false)
            ->assertJsonPath('certificate.configured', false)
            ->assertJsonPath('ready_to_emit', false)
            ->assertJsonPath('electronic_invoicing_enabled', true)
            ->assertJsonPath('environment', FiscalEnvironment::HABILITACION);
        $body = $response->json();
        $this->assertSame(false, $body['resolutions'][DocumentType::FEV]);
        $this->assertSame(false, $body['resolutions'][DocumentType::DEE_POS]);
    }

    public function test_healthcheck_reports_ready_when_everything_is_in_place(): void
    {
        $company = $this->seedCompany();
        DianSoftwareCredential::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'software_pin_secret_ref' => 'ref:hab/pin',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        ]);
        DianResolution::create([
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
            'active' => true,
        ]);

        $response = $this->getJson('/api/electronic-invoicing/healthcheck');
        $response->assertStatus(200)
            ->assertJsonPath('company_profile.configured', true)
            ->assertJsonPath('software_credential.configured', true)
            ->assertJsonPath('software_credential.pin_reference_present', true)
            ->assertJsonPath('software_credential.pin_resolvable', true)
            ->assertJsonPath('resolutions.' . DocumentType::DEE_POS, true)
            ->assertJsonPath('resolutions.' . DocumentType::FEV, false)
            ->assertJsonPath('ready_to_emit', true);
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'environment' => FiscalEnvironment::HABILITACION,
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@campoverde.local',
            'active' => true,
        ], $overrides);
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
}
