<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Infrastructure\ElectronicInvoicing\Certificates\P12CertificateProvider;
use App\Models\CompanyFiscalProfile;
use App\Services\ElectronicInvoicing\Certificate\FiscalCertificateService;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateSecretStore;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateStorage;
use App\Services\ElectronicInvoicing\Certificate\P12CertificateParser;
use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

class P12CertificateProviderTest extends TestCase
{
    use RefreshDatabase;

    /** @var P12Factory */
    private static $factory;
    private static array $artifact = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$factory = new P12Factory();
        self::$artifact = self::$factory->generate(['subject_cn' => 'Campo Verde', 'password' => 'pw-pv']);
    }

    public function test_throws_when_no_active_certificate(): void
    {
        $company = $this->seedCompany();
        $provider = $this->newProvider();

        $this->expectException(RuntimeException::class);
        $provider->active($company->id, FiscalEnvironment::HABILITACION);
    }

    public function test_active_returns_certificate_metadata(): void
    {
        [$company, $storage, $secrets, $provider, $cert] = $this->seedActiveCertificate();

        $metadata = $provider->active($company->id, FiscalEnvironment::HABILITACION);

        $this->assertSame('Campo Verde', $metadata['subject_cn']);
        $this->assertSame(self::$artifact['fingerprint_sha256'], $metadata['fingerprint_sha256']);
        $this->assertStringStartsWith('cert-', $metadata['alias']);
    }

    public function test_load_returns_certificate_and_private_key_in_pem(): void
    {
        [$company, $storage, $secrets, $provider, $cert] = $this->seedActiveCertificate();

        $material = $provider->load($company->id, FiscalEnvironment::HABILITACION);

        $this->assertArrayHasKey('certificate', $material);
        $this->assertArrayHasKey('private_key', $material);
        $this->assertArrayHasKey('chain_pem', $material);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $material['certificate']);
        $this->assertStringContainsString('BEGIN', $material['private_key']);
        $this->assertStringContainsString('PRIVATE KEY', $material['private_key']);
    }

    public function test_load_throws_when_storage_artifact_is_missing(): void
    {
        [$company, $storage, $secrets, $provider, $cert] = $this->seedActiveCertificate();
        // Forget the bytes so the storage cannot resolve the path.
        $storage->delete($cert->getRawOriginal('storage_path'));

        $this->expectException(RuntimeException::class);
        $provider->load($company->id, FiscalEnvironment::HABILITACION);
    }

    public function test_load_throws_when_secret_password_is_missing(): void
    {
        [$company, $storage, $secrets, $provider, $cert] = $this->seedActiveCertificate();
        $secrets->forget($cert->getRawOriginal('password_secret_ref'));

        $this->expectException(RuntimeException::class);
        $provider->load($company->id, FiscalEnvironment::HABILITACION);
    }

    private function newProvider(?InMemoryCertificateStorage $storage = null, ?InMemoryCertificateSecretStore $secrets = null): P12CertificateProvider
    {
        return new P12CertificateProvider($storage ?? new InMemoryCertificateStorage(), $secrets ?? new InMemoryCertificateSecretStore());
    }

    /**
     * @return array{0: CompanyFiscalProfile, 1: InMemoryCertificateStorage, 2: InMemoryCertificateSecretStore, 3: P12CertificateProvider, 4: \App\Models\FiscalCertificate}
     */
    private function seedActiveCertificate(): array
    {
        $company = $this->seedCompany();
        $storage = new InMemoryCertificateStorage();
        $secrets = new InMemoryCertificateSecretStore();
        $service = new FiscalCertificateService(new P12CertificateParser(), $storage, $secrets);

        $cert = $service->upload(
            $company->id,
            FiscalEnvironment::HABILITACION,
            self::$artifact['p12'],
            self::$artifact['password']
        );
        $service->activate($cert->id);

        $provider = new P12CertificateProvider($storage, $secrets);

        return [$company, $storage, $secrets, $provider, $cert->refresh()];
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
