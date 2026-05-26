<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\FiscalCertificate;
use App\Models\DianSoftwareCredential;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyFiscalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_minimum_fiscal_fields(): void
    {
        $profile = CompanyFiscalProfile::create($this->profilePayload());

        $this->assertNotNull($profile->id);
        $this->assertSame('900123456', $profile->nit);
        $this->assertSame(FiscalEnvironment::HABILITACION, $profile->environment);
        $this->assertFalse($profile->active);
        $this->assertSame(['O-13', 'O-15'], $profile->tax_responsibilities);
    }

    public function test_nit_environment_pair_must_be_unique(): void
    {
        CompanyFiscalProfile::create($this->profilePayload());

        $this->expectException(QueryException::class);
        CompanyFiscalProfile::create($this->profilePayload([
            'legal_name' => 'Duplicate',
        ]));
    }

    public function test_same_nit_allowed_across_environments(): void
    {
        $hab = CompanyFiscalProfile::create($this->profilePayload());
        $prod = CompanyFiscalProfile::create($this->profilePayload([
            'environment' => FiscalEnvironment::PRODUCTION,
        ]));

        $this->assertNotEquals($hab->id, $prod->id);
        $this->assertSame($hab->nit, $prod->nit);
    }

    public function test_relations_to_certificates_credentials_and_resolutions(): void
    {
        $profile = CompanyFiscalProfile::create($this->profilePayload());

        FiscalCertificate::create([
            'company_id' => $profile->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'subject_cn' => 'CN=Campo Verde',
            'issuer_cn' => 'CN=Certicamara',
            'serial_number' => 'SN-001',
            'not_before' => now()->subDay(),
            'not_after' => now()->addYear(),
            'fingerprint_sha256' => str_repeat('a', 64),
            'storage_path' => '/fiscal/cert.p12',
            'password_secret_ref' => 'ref:hab/cert-pw',
            'active' => true,
            'loaded_at' => now(),
        ]);

        DianSoftwareCredential::create([
            'company_id' => $profile->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'software_id' => '00000000-0000-0000-0000-000000000001',
            'software_pin_secret_ref' => 'ref:hab/pin',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        ]);

        $profile->refresh();
        $this->assertCount(1, $profile->certificates);
        $this->assertCount(1, $profile->softwareCredentials);
        $this->assertNotNull($profile->activeCertificate(FiscalEnvironment::HABILITACION));
    }

    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13', 'O-15'],
            'economic_activity_code' => '5511',
            'address_line' => 'Km 5 Via El Edén',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@campoverde.local',
            'phone' => '+57 300 000 0000',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => false,
        ], $overrides);
    }
}
