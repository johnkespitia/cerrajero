<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\FiscalCertificate;
use App\Services\ElectronicInvoicing\Certificate\FiscalCertificateService;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateSecretStore;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateStorage;
use App\Services\ElectronicInvoicing\Certificate\P12CertificateParser;
use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

class FiscalCertificateServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var P12Factory */
    private static $p12Factory;
    /** @var array<int, array{p12: string, password: string, fingerprint_sha256: string, cert_pem: string}> */
    private static array $artifacts = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$p12Factory = new P12Factory();
    }

    public function test_upload_creates_certificate_in_inactive_state(): void
    {
        $company = $this->seedCompany();
        $service = $this->buildService();
        $artifact = $this->artifact('alpha');

        $cert = $service->upload(
            $company->id,
            FiscalEnvironment::HABILITACION,
            $artifact['p12'],
            $artifact['password']
        );

        $this->assertFalse($cert->active);
        $this->assertSame($artifact['fingerprint_sha256'], $cert->fingerprint_sha256);
        $this->assertStringStartsWith('memory://fiscal/', $cert->getRawOriginal('storage_path'));
        $this->assertStringStartsWith('inmem-secret://', $cert->getRawOriginal('password_secret_ref'));
    }

    public function test_upload_rejects_duplicate_fingerprint(): void
    {
        $company = $this->seedCompany();
        $service = $this->buildService();
        $artifact = $this->artifact('alpha');

        $service->upload($company->id, FiscalEnvironment::HABILITACION, $artifact['p12'], $artifact['password']);

        try {
            $service->upload($company->id, FiscalEnvironment::HABILITACION, $artifact['p12'], $artifact['password']);
            $this->fail('Expected duplicate fingerprint exception');
        } catch (InvalidCertificateException $e) {
            $this->assertSame(InvalidCertificateException::CODE_DUPLICATE, $e->errorCode());
        }
    }

    public function test_activate_marks_target_active_and_deactivates_others(): void
    {
        $company = $this->seedCompany();
        $service = $this->buildService();

        $alpha = $service->upload($company->id, FiscalEnvironment::HABILITACION, $this->artifact('alpha')['p12'], $this->artifact('alpha')['password']);
        $beta  = $service->upload($company->id, FiscalEnvironment::HABILITACION, $this->artifact('beta')['p12'], $this->artifact('beta')['password']);

        $service->activate($alpha->id);
        $this->assertTrue(FiscalCertificate::find($alpha->id)->active);
        $this->assertFalse(FiscalCertificate::find($beta->id)->active);

        $service->activate($beta->id);
        $this->assertFalse(FiscalCertificate::find($alpha->id)->active);
        $this->assertTrue(FiscalCertificate::find($beta->id)->active);
    }

    public function test_delete_cannot_remove_active_certificate(): void
    {
        $company = $this->seedCompany();
        $service = $this->buildService();
        $artifact = $this->artifact('alpha');

        $cert = $service->upload($company->id, FiscalEnvironment::HABILITACION, $artifact['p12'], $artifact['password']);
        $service->activate($cert->id);

        $this->expectException(\DomainException::class);
        $service->delete($cert->id);
    }

    public function test_delete_removes_inactive_certificate_and_storage(): void
    {
        $company = $this->seedCompany();
        $storage = new InMemoryCertificateStorage();
        $secrets = new InMemoryCertificateSecretStore();
        $service = new FiscalCertificateService(new P12CertificateParser(), $storage, $secrets);

        $artifact = $this->artifact('alpha');
        $cert = $service->upload($company->id, FiscalEnvironment::HABILITACION, $artifact['p12'], $artifact['password']);
        $storagePath = $cert->getRawOriginal('storage_path');
        $secretRef = $cert->getRawOriginal('password_secret_ref');

        $this->assertNotNull($storage->retrieve($storagePath));
        $this->assertSame($artifact['password'], $secrets->get($secretRef));

        $service->delete($cert->id);

        $this->assertNull(FiscalCertificate::find($cert->id));
        $this->assertNull($storage->retrieve($storagePath));
        $this->assertNull($secrets->get($secretRef));
    }

    private function buildService(): FiscalCertificateService
    {
        return new FiscalCertificateService(
            new P12CertificateParser(),
            new InMemoryCertificateStorage(),
            new InMemoryCertificateSecretStore()
        );
    }

    private function artifact(string $key): array
    {
        if (! isset(self::$artifacts[$key])) {
            self::$artifacts[$key] = self::$p12Factory->generate([
                'subject_cn' => 'Cert ' . $key,
                'password' => 'pw-' . $key,
            ]);
        }

        return self::$artifacts[$key];
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
