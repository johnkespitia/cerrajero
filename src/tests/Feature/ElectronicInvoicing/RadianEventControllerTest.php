<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Infrastructure\ElectronicInvoicing\Certificates\P12CertificateProvider;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Models\User;
use App\Services\ElectronicInvoicing\Certificate\CertificateSecretStoreInterface;
use App\Services\ElectronicInvoicing\Certificate\CertificateStorageInterface;
use App\Services\ElectronicInvoicing\Certificate\FiscalCertificateService;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateSecretStore;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateStorage;
use App\Services\ElectronicInvoicing\Certificate\P12CertificateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

class RadianEventControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    private static array $artifact = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$artifact = (new P12Factory())->generate(['subject_cn' => 'RadianCtrl', 'password' => 'pw']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('electronic-invoicing.signature', [
            'algorithm' => 'RSA-SHA256',
            'canonicalization' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
            'policy_oid' => '2.16.170.1.5.2.1',
            'policy_url' => 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf',
            'policy_hash_b64' => 'dMqkBgDfJ+CMb6tJM7gQUFA0R5o=',
        ]);
        $this->bindInMemoryStack();
    }

    public function test_post_emits_radian_event_when_authorized(): void
    {
        $this->actingAsWith(['electronic_invoicing.radian']);
        $document = $this->seedAcceptedFev();
        $this->scriptSoap('sendEventUpdateStatus', [
            'IsValid' => 'true', 'StatusCode' => '00', 'XmlBytes' => base64_encode('<AR/>'),
        ]);

        $response = $this->postJson("/api/electronic-invoicing/documents/{$document->id}/radian/030");
        $response->assertStatus(201)
            ->assertJsonPath('event.event_code', '030')
            ->assertJsonPath('event.status', 'dian_accepted');
    }

    public function test_post_returns_422_for_invalid_event_code(): void
    {
        $this->actingAsWith(['electronic_invoicing.radian']);
        $document = $this->seedAcceptedFev();
        $response = $this->postJson("/api/electronic-invoicing/documents/{$document->id}/radian/099");
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'radian_invalid_code');
    }

    public function test_post_returns_422_when_document_not_accepted(): void
    {
        $this->actingAsWith(['electronic_invoicing.radian']);
        $document = $this->seedAcceptedFev();
        $document->status = DocumentStatus::DIAN_VALIDATING;
        $document->save();

        $response = $this->postJson("/api/electronic-invoicing/documents/{$document->id}/radian/030");
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'radian_document_not_accepted');
    }

    public function test_post_returns_401_without_permission(): void
    {
        $this->actingAsWith([]);
        $document = $this->seedAcceptedFev();
        $response = $this->postJson("/api/electronic-invoicing/documents/{$document->id}/radian/030");
        $response->assertStatus(401);
    }

    public function test_get_lists_radian_events_for_document(): void
    {
        $this->actingAsWith(['electronic_invoicing.radian', 'electronic_invoicing.list']);
        $document = $this->seedAcceptedFev();
        $this->scriptSoap('sendEventUpdateStatus', [
            'IsValid' => 'true', 'StatusCode' => '00',
        ]);
        $this->postJson("/api/electronic-invoicing/documents/{$document->id}/radian/030")->assertStatus(201);

        $response = $this->getJson("/api/electronic-invoicing/documents/{$document->id}/radian");
        $response->assertStatus(200)
            ->assertJsonPath('events.0.event_code', '030');
    }

    private function bindInMemoryStack(): void
    {
        // Storages
        $this->app->instance(CertificateStorageInterface::class, new InMemoryCertificateStorage());
        $this->app->instance(CertificateSecretStoreInterface::class, new InMemoryCertificateSecretStore());

        // Soap client double
        $soap = new FakeDianSoapClient();
        $this->app->instance(DianSoapClientInterface::class, $soap);
    }

    private function scriptSoap(string $op, array $result): void
    {
        $soap = $this->app->make(DianSoapClientInterface::class);
        $soap->script($op, ['result' => $result]);
    }

    private function actingAsWith(array $perms): void
    {
        $user = User::create([
            'name' => 'tester',
            'email' => 'radian-' . bin2hex(random_bytes(3)) . '@test.local',
            'password' => bcrypt('pwd'),
        ]);
        $this->grantElectronicInvoicingPermissions($user, $perms);
        $this->actingAs($user, 'sanctum');
    }

    private function seedAcceptedFev(): ElectronicDocument
    {
        if (! CompanyFiscalProfile::query()->where('nit', '900123456')->exists()) {
            $company = CompanyFiscalProfile::create([
                'legal_name' => 'CV', 'trade_name' => 'CV', 'nit' => '900123456', 'dv' => 1,
                'tax_regime_code' => '48', 'tax_responsibilities' => ['O-13'],
                'address_line' => 'Km 5', 'city_code_dian' => '63190', 'country_code' => 'CO',
                'email' => 'fiscal@cv.local', 'environment' => FiscalEnvironment::HABILITACION,
                'active' => true,
            ]);

            $svc = new FiscalCertificateService(
                new P12CertificateParser(),
                $this->app->make(CertificateStorageInterface::class),
                $this->app->make(CertificateSecretStoreInterface::class),
            );
            $cert = $svc->upload($company->id, FiscalEnvironment::HABILITACION, self::$artifact['p12'], self::$artifact['password']);
            $svc->activate($cert->id);
        }
        $company = CompanyFiscalProfile::query()->where('nit', '900123456')->first();
        $resolution = DianResolution::create([
            'company_id' => $company->id, 'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV, 'prefix' => 'SETP',
            'resolution_number' => '18760000001', 'resolution_date' => '2026-01-01',
            'from_number' => 990000001, 'to_number' => 990010000,
            'valid_from' => '2026-01-01', 'valid_to' => '2099-12-31',
            'current_number' => 0, 'active' => true,
        ]);

        return ElectronicDocument::create([
            'company_id' => $company->id, 'resolution_id' => $resolution->id,
            'document_type' => DocumentType::FEV,
            'reference_code' => 'ref-' . bin2hex(random_bytes(4)),
            'dian_number' => '990000001',
            'cufe_cude' => str_repeat('a', 96),
            'status' => DocumentStatus::DIAN_ACCEPTED,
            'environment' => FiscalEnvironment::HABILITACION,
            'subtotal' => '100000.00', 'total_taxes' => '19000.00', 'total' => '119000.00',
            'currency_code' => 'COP', 'issue_date' => now(),
            'source_type' => 'reservation', 'source_id' => 1,
        ]);
    }
}
