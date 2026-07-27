<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\FiscalCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    /** @var P12Factory */
    private static $factory;
    private static array $artifact = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$factory = new P12Factory();
        self::$artifact = self::$factory->generate(['subject_cn' => 'Campo Verde', 'password' => 'pw-strong']);
    }

    public function test_admin_can_upload_a_certificate_and_lists_inactive_by_default(): void
    {
        $company = $this->seedCompany();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/electronic-invoicing/admin/certificates', [
            'company_id' => $company->id,
            'password' => self::$artifact['password'],
            'p12_base64' => base64_encode(self::$artifact['p12']),
            'environment' => FiscalEnvironment::HABILITACION,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('certificate.active', false)
            ->assertJsonPath('certificate.fingerprint_sha256', self::$artifact['fingerprint_sha256']);
        $payload = $response->json('certificate');
        $this->assertArrayNotHasKey('password_secret_ref', $payload);
        $this->assertArrayNotHasKey('storage_path', $payload);

        $list = $this->getJson(sprintf('/api/electronic-invoicing/admin/certificates?company_id=%d&environment=habilitacion', $company->id));
        $list->assertStatus(200)
            ->assertJsonCount(1, 'certificates')
            ->assertJsonPath('certificates.0.subject_cn', 'Campo Verde');
    }

    public function test_upload_rejects_invalid_p12(): void
    {
        $company = $this->seedCompany();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/electronic-invoicing/admin/certificates', [
            'company_id' => $company->id,
            'password' => 'whatever',
            'p12_base64' => base64_encode('not-a-p12'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('electronic_document_error.code', 'certificate_cannot_open');
    }

    public function test_activate_flips_active_and_deactivates_others(): void
    {
        $company = $this->seedCompany();
        $this->actingAsAdmin();

        $betaArtifact = self::$factory->generate(['subject_cn' => 'Campo Verde Beta', 'password' => 'pw-beta']);

        $first = $this->postJson('/api/electronic-invoicing/admin/certificates', [
            'company_id' => $company->id,
            'password' => self::$artifact['password'],
            'p12_base64' => base64_encode(self::$artifact['p12']),
        ])->assertStatus(201)->json('certificate.id');

        $second = $this->postJson('/api/electronic-invoicing/admin/certificates', [
            'company_id' => $company->id,
            'password' => $betaArtifact['password'],
            'p12_base64' => base64_encode($betaArtifact['p12']),
        ])->assertStatus(201)->json('certificate.id');

        $this->postJson(sprintf('/api/electronic-invoicing/admin/certificates/%d/activate', $first))
            ->assertStatus(200)
            ->assertJsonPath('certificate.active', true);

        $this->postJson(sprintf('/api/electronic-invoicing/admin/certificates/%d/activate', $second))
            ->assertStatus(200)
            ->assertJsonPath('certificate.active', true);

        $this->assertFalse(FiscalCertificate::find($first)->active);
        $this->assertTrue(FiscalCertificate::find($second)->active);
    }

    public function test_delete_rejects_active_certificate_with_409(): void
    {
        $company = $this->seedCompany();
        $this->actingAsAdmin();

        $certId = $this->postJson('/api/electronic-invoicing/admin/certificates', [
            'company_id' => $company->id,
            'password' => self::$artifact['password'],
            'p12_base64' => base64_encode(self::$artifact['p12']),
        ])->assertStatus(201)->json('certificate.id');

        $this->postJson(sprintf('/api/electronic-invoicing/admin/certificates/%d/activate', $certId));

        $this->deleteJson(sprintf('/api/electronic-invoicing/admin/certificates/%d', $certId))
            ->assertStatus(409)
            ->assertJsonPath('electronic_document_error.code', 'certificate_active');
    }

    public function test_endpoints_require_admin_permission(): void
    {
        $company = $this->seedCompany();
        $user = $this->makeUser('viewer@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.list']);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/electronic-invoicing/admin/certificates', [
            'company_id' => $company->id,
            'password' => 'x',
            'p12_base64' => 'abc',
        ])->assertStatus(401);
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
}
