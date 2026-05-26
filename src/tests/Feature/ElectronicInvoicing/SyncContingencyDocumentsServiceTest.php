<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Infrastructure\ElectronicInvoicing\Certificates\P12CertificateProvider;
use App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator;
use App\Infrastructure\ElectronicInvoicing\Cufe\SoftwareSecurityCodeCalculator;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocumentAcquirer;
use App\Services\ElectronicInvoicing\Certificate\FiscalCertificateService;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateSecretStore;
use App\Services\ElectronicInvoicing\Certificate\InMemoryCertificateStorage;
use App\Services\ElectronicInvoicing\Certificate\P12CertificateParser;
use App\Services\ElectronicInvoicing\Contingency\ContingencyManager;
use App\Services\ElectronicInvoicing\Contingency\InMemoryCircuitBreaker;
use App\Services\ElectronicInvoicing\DianDispatcher;
use App\Services\ElectronicInvoicing\DocumentAssembler;
use App\Services\ElectronicInvoicing\DocumentEmitter;
use App\Services\ElectronicInvoicing\NumberingAllocator;
use App\Services\ElectronicInvoicing\SigningCoordinator;
use App\Services\ElectronicInvoicing\Storage\InMemoryDianResponseStorage;
use App\Services\ElectronicInvoicing\Storage\InMemorySignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\SyncContingencyDocumentsService;
use App\Services\ElectronicInvoicing\UblBuilderRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

class SyncContingencyDocumentsServiceTest extends TestCase
{
    use RefreshDatabase;

    private static array $artifact = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$artifact = (new P12Factory())->generate(['subject_cn' => 'Sync', 'password' => 'pw']);
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

    public function test_sync_pending_dispatches_contingency_documents_within_window(): void
    {
        [$emitter, $service, $soap] = $this->buildPipeline();
        $manager = new ContingencyManager(new InMemoryCircuitBreaker());

        // Emite documento normalmente y luego lo marca como contingencia
        // simulando que el dispatcher fallo y el ContingencyManager
        // capturó al documento.
        $document = $emitter->emit($this->fevContext());
        $document = $manager->markContingencyDocument(
            tap($document, function ($d): void {
                $d->status = DocumentStatus::DRAFT;
                $d->save();
            }),
            'dian_unreachable'
        );

        $soap->script('sendBillSync', [
            'result' => [
                'IsValid' => 'true',
                'StatusCode' => '00',
                'XmlBytes' => base64_encode('<AR/>'),
            ],
        ]);

        $summary = $service->syncPending();
        $this->assertSame(1, $summary['synced']);
        $document->refresh();
        $this->assertSame(DocumentStatus::DIAN_ACCEPTED, $document->status);
        $this->assertNotNull($document->contingency_synced_at);
        $events = $document->events()->pluck('event_type')->all();
        $this->assertContains('contingency_synced', $events);
        $this->assertContains('dian_accepted', $events);
    }

    public function test_sync_pending_dead_letters_documents_over_48h(): void
    {
        [$emitter, $service] = $this->buildPipeline();
        $manager = new ContingencyManager(new InMemoryCircuitBreaker());

        $document = $emitter->emit($this->fevContext());
        $document = $manager->markContingencyDocument(
            tap($document, function ($d): void {
                $d->status = DocumentStatus::DRAFT;
                $d->save();
            })
        );
        $document->contingency_emitted_at = Carbon::now()->subHours(50);
        $document->save();

        $summary = $service->syncPending();
        $this->assertSame(1, $summary['deadLettered']);
        $document->refresh();
        $this->assertSame(DocumentStatus::DEAD_LETTER, $document->status);
    }

    public function test_sync_pending_counts_failures(): void
    {
        [$emitter, $service, $soap] = $this->buildPipeline();
        $manager = new ContingencyManager(new InMemoryCircuitBreaker());

        $document = $emitter->emit($this->fevContext());
        $document = $manager->markContingencyDocument(tap($document, function ($d): void {
            $d->status = DocumentStatus::DRAFT;
            $d->save();
        }));

        $soap->fail('sendBillSync', new \RuntimeException('connection reset'));

        $summary = $service->syncPending();
        $this->assertSame(1, $summary['failed']);
        $document->refresh();
        $this->assertContains('error', $document->events()->pluck('event_type')->all());
    }

    /**
     * @return array{0: DocumentEmitter, 1: SyncContingencyDocumentsService, 2: FakeDianSoapClient}
     */
    private function buildPipeline(): array
    {
        $unsignedStorage = new InMemoryUnsignedXmlStorage();
        $signedStorage = new InMemorySignedXmlStorage();
        $responseStorage = new InMemoryDianResponseStorage();
        $certStorage = new InMemoryCertificateStorage();
        $secretStore = new InMemoryCertificateSecretStore();
        $soap = new FakeDianSoapClient();

        $svc = new FiscalCertificateService(new P12CertificateParser(), $certStorage, $secretStore);
        $company = $this->seedCompany();
        $cert = $svc->upload($company->id, FiscalEnvironment::HABILITACION, self::$artifact['p12'], self::$artifact['password']);
        $svc->activate($cert->id);

        $provider = new P12CertificateProvider($certStorage, $secretStore);
        $signer = new XadesEpesSigner($provider, (array) config('electronic-invoicing.signature'));
        $signingCoordinator = new SigningCoordinator($unsignedStorage, $signedStorage, $provider, $signer);
        $dispatcher = new DianDispatcher($signedStorage, $responseStorage, $soap);

        $emitter = new DocumentEmitter(
            new DocumentAssembler(),
            new Sha384CufeCalculator(),
            new SoftwareSecurityCodeCalculator(),
            UblBuilderRegistry::default(),
            $unsignedStorage,
            null,
            null,
            $signingCoordinator,
            true,
            $dispatcher,
            false,
            'sync'
        );

        $service = new SyncContingencyDocumentsService(
            $signingCoordinator,
            $dispatcher,
            new \App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder(),
            new \App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger(),
            48
        );

        return [$emitter, $service, $soap];
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

    private function fevContext(): array
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
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
            'current_number' => 0,
            'active' => true,
        ]);
        $acquirer = ElectronicDocumentAcquirer::create([
            'document_type' => '31',
            'document_number' => '800111222',
            'dv' => 3,
            'legal_name' => 'Cliente B2B SAS',
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Cra 50',
            'city_code_dian' => '11001',
            'country_code' => 'CO',
            'email' => 'b2b@cliente.local',
        ]);
        $numbering = (new NumberingAllocator())->allocate(
            $company->id,
            FiscalEnvironment::HABILITACION,
            DocumentType::FEV
        );

        return [
            'company' => $company,
            'document_type' => DocumentType::FEV,
            'environment' => FiscalEnvironment::HABILITACION,
            'numbering' => $numbering,
            'acquirer' => $acquirer,
            'acquirer_id' => $acquirer->id,
            'issued_at' => Carbon::create(2026, 3, 26, 10, 30, 0),
            'currency' => 'COP',
            'lines' => [[
                'sequence' => 1,
                'description' => 'Test',
                'quantity' => '1',
                'unit_price' => '100000.00',
                'line_total' => '100000.00',
                'tax_amount' => '19000.00',
                'taxable_amount' => '100000.00',
                'tax_percent' => '19.00',
            ]],
            'totals' => [
                'line_extension_amount' => '100000.00',
                'tax_exclusive_amount' => '100000.00',
                'tax_inclusive_amount' => '119000.00',
                'payable_amount' => '119000.00',
            ],
            'taxes' => [[
                'code' => '01',
                'name' => 'IVA',
                'percent' => '19.00',
                'taxable_amount' => '100000.00',
                'tax_amount' => '19000.00',
            ]],
            'payment' => ['means_code' => '10', 'terms_code' => '1'],
            'cufe_signing' => ['clave_tecnica' => (string) $resolution->technical_key],
            'source_meta' => ['source_type' => 'reservation', 'source_id' => 1],
        ];
    }
}
