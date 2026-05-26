<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Enums\RadianEventCode;
use App\Domain\ElectronicInvoicing\Enums\RadianEventStatus;
use App\Infrastructure\ElectronicInvoicing\Certificates\P12CertificateProvider;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\Certificate\FiscalCertificateService;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateSecretStore;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateStorage;
use App\Services\ElectronicInvoicing\Certificate\P12CertificateParser;
use App\Services\ElectronicInvoicing\Exceptions\RadianEventException;
use App\Services\ElectronicInvoicing\Radian\RadianEventService;
use App\Services\ElectronicInvoicing\Storage\InMemoryDianResponseStorage;
use App\Services\ElectronicInvoicing\Storage\InMemorySignedXmlStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

class RadianEventServiceTest extends TestCase
{
    use RefreshDatabase;

    private static array $artifact = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$artifact = (new P12Factory())->generate(['subject_cn' => 'Radian', 'password' => 'pw']);
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
    }

    public function test_emit_persists_dian_event_and_marks_accepted_when_dian_accepts(): void
    {
        [$service, $soap] = $this->buildService();
        $document = $this->seedAcceptedFev();

        $soap->script('sendEventUpdateStatus', [
            'result' => [
                'IsValid' => 'true',
                'StatusCode' => '00',
                'XmlBytes' => base64_encode('<AR/>'),
            ],
        ]);

        $event = $service->emit($document, RadianEventCode::RECEIPT_ACKNOWLEDGED);

        $this->assertSame(RadianEventStatus::DIAN_ACCEPTED, $event->status);
        $this->assertSame('030', $event->event_code);
        $this->assertTrue((bool) $event->dian_is_valid);
        $this->assertNotNull($event->xml_signed_path);
        $this->assertNotNull($event->dian_application_response_path);

        $eventTypes = $document->events()->pluck('event_type')->all();
        $this->assertContains('radian_event_emitted', $eventTypes);
        $this->assertContains('radian_event_synced', $eventTypes);
    }

    public function test_emit_marks_rejected_when_dian_rejects(): void
    {
        [$service, $soap] = $this->buildService();
        $document = $this->seedAcceptedFev();
        $soap->script('sendEventUpdateStatus', [
            'result' => [
                'IsValid' => 'false',
                'StatusCode' => '99',
                'ErrorMessage' => [['code' => 'X1', 'message' => 'XSD invalid']],
            ],
        ]);

        $event = $service->emit($document, RadianEventCode::GOOD_OR_SERVICE_ACKNOWLEDGED);
        $this->assertSame(RadianEventStatus::DIAN_REJECTED, $event->status);
        $this->assertFalse((bool) $event->dian_is_valid);
        $this->assertSame('99', $event->dian_status_code);
        $this->assertNotEmpty($event->dian_error_messages);
    }

    public function test_emit_rejects_non_fev_documents(): void
    {
        [$service] = $this->buildService();
        $document = $this->seedAcceptedFev();
        $document->document_type = DocumentType::DEE_POS;
        $document->save();

        $this->expectException(RadianEventException::class);
        $service->emit($document, RadianEventCode::RECEIPT_ACKNOWLEDGED);
    }

    public function test_emit_rejects_documents_not_yet_accepted(): void
    {
        [$service] = $this->buildService();
        $document = $this->seedAcceptedFev();
        $document->status = DocumentStatus::DIAN_VALIDATING;
        $document->save();

        $this->expectException(RadianEventException::class);
        $service->emit($document, RadianEventCode::RECEIPT_ACKNOWLEDGED);
    }

    public function test_emit_rejects_duplicate_accepted_event_code(): void
    {
        [$service, $soap] = $this->buildService();
        $document = $this->seedAcceptedFev();
        $soap->script('sendEventUpdateStatus', [
            'result' => ['IsValid' => 'true', 'StatusCode' => '00'],
        ]);
        $service->emit($document, RadianEventCode::RECEIPT_ACKNOWLEDGED);

        $this->expectException(RadianEventException::class);
        $service->emit($document, RadianEventCode::RECEIPT_ACKNOWLEDGED);
    }

    public function test_emit_throws_soap_failed_and_logs_error_when_dispatch_fails(): void
    {
        [$service, $soap] = $this->buildService();
        $document = $this->seedAcceptedFev();
        $soap->fail('sendEventUpdateStatus', new \RuntimeException('connection refused'));

        try {
            $service->emit($document, RadianEventCode::CLAIM);
            $this->fail('Expected RadianEventException to be thrown');
        } catch (RadianEventException $e) {
            $this->assertSame('radian_soap_failed', $e->errorCode());
        }
        $this->assertContains('error', $document->events()->pluck('event_type')->all());
    }

    public function test_list_for_document_returns_emitted_events(): void
    {
        [$service, $soap] = $this->buildService();
        $document = $this->seedAcceptedFev();
        $soap->script('sendEventUpdateStatus', [
            'result' => ['IsValid' => 'true', 'StatusCode' => '00'],
        ]);
        $service->emit($document, RadianEventCode::RECEIPT_ACKNOWLEDGED);

        $list = $service->listForDocument((int) $document->id);
        $this->assertCount(1, $list);
        $this->assertSame('030', $list[0]['event_code']);
    }

    /**
     * @return array{0: RadianEventService, 1: FakeDianSoapClient}
     */
    private function buildService(): array
    {
        $certStorage = new InMemoryCertificateStorage();
        $secretStore = new InMemoryCertificateSecretStore();
        $svc = new FiscalCertificateService(new P12CertificateParser(), $certStorage, $secretStore);
        $company = $this->seedCompany();
        $cert = $svc->upload(
            $company->id,
            FiscalEnvironment::HABILITACION,
            self::$artifact['p12'],
            self::$artifact['password']
        );
        $svc->activate($cert->id);

        $provider = new P12CertificateProvider($certStorage, $secretStore);
        $signer = new XadesEpesSigner($provider, (array) config('electronic-invoicing.signature'));
        $soap = new FakeDianSoapClient();
        $service = new RadianEventService(
            $provider,
            $signer,
            new InMemorySignedXmlStorage(),
            new InMemoryDianResponseStorage(),
            $soap
        );

        return [$service, $soap];
    }

    private function seedCompany(): CompanyFiscalProfile
    {
        if ($existing = CompanyFiscalProfile::query()->where('nit', '900123456')->first()) {
            return $existing;
        }

        return CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@cv.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
    }

    private function seedAcceptedFev(): ElectronicDocument
    {
        $company = $this->seedCompany();
        $resolution = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000001,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'current_number' => 0,
            'active' => true,
        ]);

        return ElectronicDocument::create([
            'company_id' => $company->id,
            'resolution_id' => $resolution->id,
            'document_type' => DocumentType::FEV,
            'reference_code' => 'ref-' . bin2hex(random_bytes(4)),
            'dian_number' => '990000001',
            'cufe_cude' => str_repeat('a', 96),
            'status' => DocumentStatus::DIAN_ACCEPTED,
            'environment' => FiscalEnvironment::HABILITACION,
            'subtotal' => '100000.00',
            'total_taxes' => '19000.00',
            'total' => '119000.00',
            'currency_code' => 'COP',
            'issue_date' => now(),
            'source_type' => 'reservation',
            'source_id' => 1,
        ]);
    }
}
