<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\FiscalCertificate;
use App\Models\User;
use App\Services\ElectronicInvoicing\Habilitacion\InMemoryTestSetReportRepository;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseDescriptor;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseEmitterInterface;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\TestCase;

/**
 * @covers \App\Http\Middleware\EnsureFiscalEnvironment
 */
class EnsureFiscalEnvironmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TestSetReportRepository::class, new InMemoryTestSetReportRepository());
        $this->app->instance(DianSoapClientInterface::class, new FakeDianSoapClient());
        $this->app->instance(TestCaseEmitterInterface::class, new class implements TestCaseEmitterInterface {
            public function emit(TestCaseDescriptor $case): array
            {
                return ['file_name' => $case->code . '.xml', 'signed_xml' => '<x/>', 'dian_number' => $case->code];
            }
        });
        config()->set('electronic-invoicing.test_set_id', 'mw-set');
    }

    public function test_request_passes_when_module_is_disabled(): void
    {
        config()->set('electronic-invoicing.enabled', false);
        config()->set('electronic-invoicing.environment', FiscalEnvironment::PRODUCTION);
        $this->seedCompany(FiscalEnvironment::HABILITACION);

        $this->actingAsAdmin();
        $soap = $this->app->make(DianSoapClientInterface::class);
        $soap->script('sendTestSetAsync', ['result' => ['IsValid' => 'true', 'StatusCode' => '00']]);

        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set', [
            'fixtures' => [['code' => 'PASS-01', 'category' => 'fev']],
        ]);
        $response->assertStatus(200);
    }

    public function test_request_passes_when_all_artifacts_match_configured_environment(): void
    {
        config()->set('electronic-invoicing.enabled', true);
        config()->set('electronic-invoicing.environment', FiscalEnvironment::HABILITACION);
        $this->seedCompany(FiscalEnvironment::HABILITACION);
        $this->seedCertificate(FiscalEnvironment::HABILITACION);
        $this->seedResolution(FiscalEnvironment::HABILITACION);

        $this->actingAsAdmin();
        $soap = $this->app->make(DianSoapClientInterface::class);
        $soap->script('sendTestSetAsync', ['result' => ['IsValid' => 'true', 'StatusCode' => '00']]);

        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set', [
            'fixtures' => [['code' => 'OK-01', 'category' => 'fev']],
        ]);
        $response->assertStatus(200);
    }

    public function test_request_blocked_with_409_when_certificate_environment_mismatches(): void
    {
        config()->set('electronic-invoicing.enabled', true);
        config()->set('electronic-invoicing.environment', FiscalEnvironment::PRODUCTION);
        $company = $this->seedCompany(FiscalEnvironment::PRODUCTION);
        $this->seedCertificate(FiscalEnvironment::HABILITACION, $company->id);
        $this->seedResolution(FiscalEnvironment::PRODUCTION, $company->id);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set', [
            'fixtures' => [['code' => 'NO-01', 'category' => 'fev']],
        ]);
        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'fiscal_environment_mismatch')
            ->assertJsonPath('configured_environment', FiscalEnvironment::PRODUCTION);
        $mismatches = $response->json('mismatches');
        $this->assertNotEmpty($mismatches);
        $kinds = array_column($mismatches, 'kind');
        $this->assertContains('fiscal_certificate', $kinds);
    }

    public function test_request_blocked_with_409_when_resolution_environment_mismatches(): void
    {
        config()->set('electronic-invoicing.enabled', true);
        config()->set('electronic-invoicing.environment', FiscalEnvironment::HABILITACION);
        $company = $this->seedCompany(FiscalEnvironment::HABILITACION);
        $this->seedCertificate(FiscalEnvironment::HABILITACION, $company->id);
        $this->seedResolution(FiscalEnvironment::PRODUCTION, $company->id);

        $this->actingAsAdmin();
        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set', [
            'fixtures' => [['code' => 'NO-02', 'category' => 'fev']],
        ]);
        $response->assertStatus(409)
            ->assertJsonPath('mismatches.0.kind', 'dian_resolution');
    }

    private function actingAsAdmin(): void
    {
        $user = User::create([
            'name' => 'admin',
            'email' => 'mw-' . bin2hex(random_bytes(3)) . '@test.local',
            'password' => bcrypt('pwd'),
        ]);
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.admin']);
        $this->actingAs($user, 'sanctum');
    }

    private function seedCompany(string $environment): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
            'legal_name' => 'Mismatch SAS',
            'trade_name' => 'Mismatch',
            'nit' => '900' . random_int(100000, 999999),
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@mw.local',
            'environment' => $environment,
            'active' => true,
        ]);
    }

    private function seedCertificate(string $environment, int $companyId = null): FiscalCertificate
    {
        return FiscalCertificate::create([
            'company_id' => $companyId ?? CompanyFiscalProfile::query()->first()->id,
            'environment' => $environment,
            'subject_cn' => 'Test',
            'issuer_cn' => 'CA',
            'serial_number' => '1',
            'not_before' => now()->subDay(),
            'not_after' => now()->addYear(),
            'fingerprint_sha256' => bin2hex(random_bytes(32)),
            'storage_path' => 'tests/dummy.p12',
            'password_secret_ref' => 'kv://dummy',
            'active' => true,
        ]);
    }

    private function seedResolution(string $environment, int $companyId = null): DianResolution
    {
        return DianResolution::create([
            'company_id' => $companyId ?? CompanyFiscalProfile::query()->first()->id,
            'environment' => $environment,
            'document_type' => 'fev',
            'prefix' => 'SETP',
            'resolution_number' => '187600000' . random_int(10, 99),
            'resolution_date' => now()->subMonth()->toDateString(),
            'from_number' => 1,
            'to_number' => 1000,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
            'current_number' => 0,
            'active' => true,
        ]);
    }
}
