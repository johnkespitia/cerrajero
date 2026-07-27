<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\FiscalCertificate;
use App\Models\User;
use App\Services\ElectronicInvoicing\Habilitacion\InMemoryTestSetReportRepository;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReport;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\TestCase;

/**
 * @covers \App\Http\Controllers\ElectronicInvoicing\CutoverReadinessController
 * @covers \App\Services\ElectronicInvoicing\Cutover\CutoverReadinessService
 */
class CutoverReadinessControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    private InMemoryTestSetReportRepository $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reports = new InMemoryTestSetReportRepository();
        $this->app->instance(TestSetReportRepository::class, $this->reports);
        config()->set('electronic-invoicing.enabled', true);
        config()->set('electronic-invoicing.signing.enabled', true);
        config()->set('electronic-invoicing.dispatch.enabled', true);
        config()->set('electronic-invoicing.environment', FiscalEnvironment::PRODUCTION);
    }

    public function test_ready_payload_when_all_preconditions_hold(): void
    {
        $this->seedFiscalStack();
        $this->storeFullPassReport();
        $this->actingAsAdmin();

        $response = $this->getJson('/api/electronic-invoicing/cutover/readiness');
        $response->assertStatus(200)
            ->assertJsonPath('ready', true)
            ->assertJsonPath('environment', FiscalEnvironment::PRODUCTION)
            ->assertJsonPath('checks.habilitacion_test_set.status', 'ok')
            ->assertJsonPath('blockers', []);
    }

    public function test_returns_409_when_test_set_was_never_run(): void
    {
        $this->seedFiscalStack();
        $this->actingAsAdmin();

        $response = $this->getJson('/api/electronic-invoicing/cutover/readiness');
        $response->assertStatus(409)
            ->assertJsonPath('ready', false)
            ->assertJsonPath('checks.habilitacion_test_set.status', 'error');
        $codes = array_column($response->json('blockers'), 'code');
        $this->assertContains('test_set_not_executed', $codes);
    }

    public function test_returns_409_when_certificate_is_near_expiry(): void
    {
        $company = $this->seedCompany();
        FiscalCertificate::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::PRODUCTION,
            'subject_cn' => 'CN',
            'issuer_cn' => 'CA',
            'serial_number' => '1',
            'not_before' => now()->subDay(),
            'not_after' => now()->addDays(30), // < 90 -> blocker
            'fingerprint_sha256' => bin2hex(random_bytes(32)),
            'storage_path' => 'p.p12',
            'password_secret_ref' => 'kv://x',
            'active' => true,
        ]);
        $this->seedResolution($company->id);
        $this->storeFullPassReport();
        $this->actingAsAdmin();

        $response = $this->getJson('/api/electronic-invoicing/cutover/readiness');
        $response->assertStatus(409);
        $codes = array_column($response->json('blockers'), 'code');
        $this->assertContains('certificate_near_expiry', $codes);
    }

    public function test_returns_409_when_resolution_range_is_too_low(): void
    {
        $company = $this->seedCompany();
        $this->seedCertificate($company->id);
        DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::PRODUCTION,
            'document_type' => 'fev',
            'prefix' => 'SETP',
            'resolution_number' => '18760000099',
            'resolution_date' => now()->subMonth()->toDateString(),
            'from_number' => 1,
            'to_number' => 100,           // small range
            'current_number' => 50,       // 50 remaining < 1000 -> blocker
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'technical_key' => 'tk',
            'active' => true,
        ]);
        $this->storeFullPassReport();
        $this->actingAsAdmin();

        $response = $this->getJson('/api/electronic-invoicing/cutover/readiness');
        $response->assertStatus(409);
        $codes = array_column($response->json('blockers'), 'code');
        $this->assertContains('resolution_range_low', $codes);
    }

    public function test_returns_409_when_test_set_acceptance_is_below_100(): void
    {
        $this->seedFiscalStack();
        $this->reports->save($this->buildReport(total: 2, accepted: 1, rejected: 1, rate: 50.0));
        $this->actingAsAdmin();

        $response = $this->getJson('/api/electronic-invoicing/cutover/readiness');
        $response->assertStatus(409);
        $codes = array_column($response->json('blockers'), 'code');
        $this->assertContains('test_set_not_full_pass', $codes);
    }

    public function test_endpoint_requires_admin_permission(): void
    {
        $user = User::create([
            'name' => 'x',
            'email' => 'ro-' . bin2hex(random_bytes(3)) . '@test.local',
            'password' => bcrypt('p'),
        ]);
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.list']);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/electronic-invoicing/cutover/readiness')->assertStatus(401);
    }

    private function seedFiscalStack(): CompanyFiscalProfile
    {
        $company = $this->seedCompany();
        $this->seedCertificate($company->id);
        $this->seedResolution($company->id);
        return $company;
    }

    private function seedCompany(): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
            'legal_name' => 'Cutover SAS',
            'trade_name' => 'Cutover',
            'nit' => '900' . random_int(100000, 999999),
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'cut@mw.local',
            'environment' => FiscalEnvironment::PRODUCTION,
            'active' => true,
        ]);
    }

    private function seedCertificate(int $companyId): FiscalCertificate
    {
        return FiscalCertificate::create([
            'company_id' => $companyId,
            'environment' => FiscalEnvironment::PRODUCTION,
            'subject_cn' => 'CN',
            'issuer_cn' => 'CA',
            'serial_number' => '1',
            'not_before' => now()->subDay(),
            'not_after' => now()->addYear(),
            'fingerprint_sha256' => bin2hex(random_bytes(32)),
            'storage_path' => 'p.p12',
            'password_secret_ref' => 'kv://x',
            'active' => true,
        ]);
    }

    private function seedResolution(int $companyId): DianResolution
    {
        return DianResolution::create([
            'company_id' => $companyId,
            'environment' => FiscalEnvironment::PRODUCTION,
            'document_type' => 'fev',
            'prefix' => 'SETP',
            'resolution_number' => '187600000' . random_int(10, 99),
            'resolution_date' => now()->subMonth()->toDateString(),
            'from_number' => 1,
            'to_number' => 100000,
            'current_number' => 0,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'technical_key' => 'tk',
            'active' => true,
        ]);
    }

    private function storeFullPassReport(): void
    {
        $this->reports->save($this->buildReport(total: 2, accepted: 2, rejected: 0, rate: 100.0));
    }

    private function buildReport(int $total, int $accepted, int $rejected, float $rate): TestSetReport
    {
        return new TestSetReport([
            'environment' => FiscalEnvironment::PRODUCTION,
            'test_set_id' => 'tset',
            'generated_at' => now()->toIso8601String(),
            'total' => $total,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'errors' => 0,
            'expectation_failures' => 0,
            'acceptance_rate' => $rate,
            'cases' => [],
        ]);
    }

    private function actingAsAdmin(): void
    {
        $user = User::create([
            'name' => 'admin',
            'email' => 'cut-' . bin2hex(random_bytes(3)) . '@test.local',
            'password' => bcrypt('pwd'),
        ]);
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.admin']);
        $this->actingAs($user, 'sanctum');
    }
}
