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
use App\Models\ElectronicDocument;
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
use App\Services\ElectronicInvoicing\Exceptions\DispatchException;
use App\Services\ElectronicInvoicing\NumberingAllocator;
use App\Services\ElectronicInvoicing\SigningCoordinator;
use App\Services\ElectronicInvoicing\Storage\InMemoryDianResponseStorage;
use App\Services\ElectronicInvoicing\Storage\InMemorySignedXmlStorage;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\UblBuilderRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\Fixtures\ElectronicInvoicing\P12Factory;
use Tests\TestCase;

/**
 * AC-20: network outage simulation.
 *
 * Goal: prove that when DIAN's SOAP endpoint becomes unreachable mid-
 * batch the platform does *not* lose any document, and the circuit
 * breaker opens so the next caller can switch to contingency.
 *
 * Scenario:
 *  1. Five FEVs are emitted in sequence.
 *  2. The first 3 succeed (`IsValid=true`, status 00).
 *  3. The next 3 attempts blow up with a SOAP `RuntimeException`. We
 *     configure `failure_threshold = 3` so the third failure flips the
 *     breaker to OPEN.
 *  4. After the breaker is OPEN, callers consult
 *     `ContingencyManager::shouldEmitInContingency()` and route the
 *     remaining documents to CONTINGENCY_EMITTED (or DEAD_LETTER if the
 *     legal window is exceeded).
 *
 * Acceptance:
 *  - Every emitted document ends in a *known terminal or staged*
 *    state. No document is left in `DRAFT`, `UBL_BUILT`, or
 *    `XADES_SIGNED`.
 *  - The circuit breaker reaches `open`.
 *  - The fiscal context is preserved (no rollback of accepted docs).
 */
class ChaosNetworkOutageTest extends TestCase
{
    use RefreshDatabase;

    private static array $artifact = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$artifact = (new P12Factory())->generate(['subject_cn' => 'Chaos Test', 'password' => 'pw']);
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

    public function test_network_outage_keeps_every_document_observable_and_opens_breaker(): void
    {
        [$emitter, $dispatcher, $soap, $contingency] = $this->buildPipeline();

        $emittedIds = [];
        for ($i = 0; $i < 5; $i++) {
            $document = $emitter->emit($this->fevContext());
            $emittedIds[] = $document->id;
        }

        // Three good responses, three network failures (open the breaker
        // with `failure_threshold = 3`, plus one extra failure already
        // counted by the third).
        $soap->script('sendBillSync', $this->okResponse());
        $soap->script('sendBillSync', $this->okResponse());
        $soap->script('sendBillSync', $this->okResponse());
        $soap->fail('sendBillSync', new RuntimeException('network unreachable'));
        $soap->fail('sendBillSync', new RuntimeException('network unreachable'));
        $soap->fail('sendBillSync', new RuntimeException('network unreachable'));

        $failures = 0;
        $accepted = 0;
        foreach ($emittedIds as $documentId) {
            $doc = ElectronicDocument::find($documentId);
            $this->assertNotNull($doc);
            try {
                $result = $dispatcher->dispatch($doc);
                $accepted += $result->status === DocumentStatus::DIAN_ACCEPTED ? 1 : 0;
            } catch (DispatchException $e) {
                $failures++;
            }
        }
        $this->assertSame(3, $accepted);
        $this->assertSame(2, $failures);

        // Last document never got to call SOAP because we already
        // exhausted the script with 2 failures; the third failure was
        // never recorded. We simulate one more independent attempt
        // hitting an unreachable endpoint to push the breaker open.
        $extra = $emitter->emit($this->fevContext());
        $soap->fail('sendBillSync', new RuntimeException('still down'));
        try {
            $dispatcher->dispatch($extra);
        } catch (DispatchException) {
            // expected
        }

        $this->assertSame(
            'open',
            $contingency->breaker()->state(),
            'Circuit breaker should be open after consecutive SOAP failures.'
        );

        $this->assertTrue($contingency->shouldEmitInContingency());

        $allDocs = ElectronicDocument::query()->whereIn('id', array_merge($emittedIds, [$extra->id]))->get();
        $allowed = [
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DIAN_REJECTED_TERMINAL,
            DocumentStatus::DIAN_REJECTED_RECOVERABLE,
            DocumentStatus::DIAN_TRACK_RECEIVED,
            DocumentStatus::SENT_TO_DIAN,
            DocumentStatus::CONTINGENCY_EMITTED,
            DocumentStatus::DEAD_LETTER,
        ];
        foreach ($allDocs as $doc) {
            $this->assertContains(
                (string) $doc->status,
                $allowed,
                sprintf('Document %d in unexpected status "%s" after outage.', $doc->id, $doc->status)
            );
        }
    }

    private function okResponse(): array
    {
        return [
            'result' => [
                'IsValid' => 'true',
                'StatusCode' => '00',
                'StatusDescription' => 'OK',
                'XmlBytes' => base64_encode('<ApplicationResponse>OK</ApplicationResponse>'),
            ],
        ];
    }

    /**
     * @return array{0:DocumentEmitter,1:DianDispatcher,2:FakeDianSoapClient,3:ContingencyManager}
     */
    private function buildPipeline(): array
    {
        $unsignedStorage = new InMemoryUnsignedXmlStorage();
        $signedStorage = new InMemorySignedXmlStorage();
        $responseStorage = new InMemoryDianResponseStorage();
        $certStorage = new InMemoryCertificateStorage();
        $secretStore = new InMemoryCertificateSecretStore();
        $soap = new FakeDianSoapClient();

        $service = new FiscalCertificateService(new P12CertificateParser(), $certStorage, $secretStore);
        $company = $this->seedCompany();
        $cert = $service->upload($company->id, FiscalEnvironment::HABILITACION, self::$artifact['p12'], self::$artifact['password']);
        $service->activate($cert->id);

        $provider = new P12CertificateProvider($certStorage, $secretStore);
        $signer = new XadesEpesSigner($provider, (array) config('electronic-invoicing.signature'));
        $signingCoordinator = new SigningCoordinator($unsignedStorage, $signedStorage, $provider, $signer);
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 3, recoverySeconds: 60);
        $contingency = new ContingencyManager($breaker);
        $dispatcher = new DianDispatcher(
            $signedStorage,
            $responseStorage,
            $soap,
            contingencyManager: $contingency,
        );

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
            null,
            false,
            'sync'
        );

        return [$emitter, $dispatcher, $soap, $contingency];
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
        $resolution = DianResolution::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->first();
        if ($resolution === null) {
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
        }
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
