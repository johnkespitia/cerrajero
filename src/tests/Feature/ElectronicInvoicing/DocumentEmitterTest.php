<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator;
use App\Infrastructure\ElectronicInvoicing\Cufe\SoftwareSecurityCodeCalculator;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocumentAcquirer;
use App\Services\ElectronicInvoicing\DocumentAssembler;
use App\Services\ElectronicInvoicing\DocumentEmitter;
use App\Services\ElectronicInvoicing\NumberingAllocator;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\UblBuilderRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentEmitterTest extends TestCase
{
    use RefreshDatabase;

    /** @var DocumentEmitter */
    private $emitter;

    /** @var InMemoryUnsignedXmlStorage */
    private $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new InMemoryUnsignedXmlStorage();
        $this->emitter = new DocumentEmitter(
            new DocumentAssembler(),
            new Sha384CufeCalculator(),
            new SoftwareSecurityCodeCalculator(),
            UblBuilderRegistry::default(),
            $this->storage
        );
    }

    public function test_emit_fev_creates_document_in_ubl_built_with_events_and_xml(): void
    {
        $company = $this->makeCompany();
        $resolution = $this->makeResolution($company, [
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
        ]);
        $acquirer = $this->makeAcquirer();
        $numbering = (new NumberingAllocator())->allocate(
            $company->id,
            FiscalEnvironment::HABILITACION,
            DocumentType::FEV
        );

        $document = $this->emitter->emit($this->fevContext($company, $resolution, $acquirer, $numbering));

        $this->assertSame(DocumentStatus::UBL_BUILT, $document->status);
        $this->assertSame(DocumentType::FEV, $document->document_type);
        $this->assertSame('900123456', $document->company->nit);
        $this->assertSame($acquirer->id, $document->acquirer_id);
        $this->assertSame((string) $numbering['number'], $document->dian_number);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $document->cufe_cude);
        $this->assertNotEmpty($document->xml_unsigned_path);
        $this->assertStringStartsWith('memory://fiscal/', $document->xml_unsigned_path);

        $xml = $this->storage->retrieve($document->xml_unsigned_path);
        $this->assertNotNull($xml);
        $this->assertStringContainsString('<cbc:ID>' . $numbering['number'] . '</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:UUID', $xml);
        $this->assertStringContainsString($document->cufe_cude, $xml);

        $events = $document->events->pluck('event_type')->all();
        $this->assertContains('queued', $events);
        $this->assertContains('cufe_calculated', $events);
        $this->assertContains('ubl_built', $events);
    }

    public function test_emit_dee_pos_does_not_require_acquirer(): void
    {
        $company = $this->makeCompany();
        $resolution = $this->makeResolution($company, [
            'document_type' => DocumentType::DEE_POS,
            'prefix' => 'POS',
            'resolution_number' => '18760000777',
        ]);
        $numbering = (new NumberingAllocator())->allocate(
            $company->id,
            FiscalEnvironment::HABILITACION,
            DocumentType::DEE_POS
        );

        $context = $this->fevContext($company, $resolution, null, $numbering);
        $context['document_type'] = DocumentType::DEE_POS;
        $context['acquirer'] = null;
        $context['acquirer_id'] = null;
        $context['cufe_signing'] = ['pin' => 'PIN-FROM-SECRET-MANAGER'];

        $document = $this->emitter->emit($context);

        $this->assertSame(DocumentStatus::UBL_BUILT, $document->status);
        $this->assertSame(DocumentType::DEE_POS, $document->document_type);
        $this->assertNull($document->acquirer_id);

        $xml = $this->storage->retrieve($document->xml_unsigned_path);
        $this->assertStringContainsString('<Invoice', $xml);
        $this->assertStringNotContainsString('AccountingCustomerParty', $xml);
    }

    public function test_emit_stores_software_security_code_when_credential_is_provided(): void
    {
        $company = $this->makeCompany();
        $resolution = $this->makeResolution($company, [
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
        ]);
        $acquirer = $this->makeAcquirer();
        $numbering = (new NumberingAllocator())->allocate(
            $company->id,
            FiscalEnvironment::HABILITACION,
            DocumentType::FEV
        );

        $context = $this->fevContext($company, $resolution, $acquirer, $numbering);
        $context['software_credential'] = [
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'pin' => 'PIN-RESOLVED-AT-RUNTIME',
        ];

        $document = $this->emitter->emit($context);

        $this->assertNotNull($document->software_security_code);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $document->software_security_code);

        $xml = $this->storage->retrieve($document->xml_unsigned_path);
        $this->assertStringContainsString($document->software_security_code, $xml);
        $this->assertStringNotContainsString('PIN-RESOLVED-AT-RUNTIME', $xml);
    }

    public function test_can_emit_reports_supported_pairs(): void
    {
        $this->assertTrue($this->emitter->canEmit(DocumentType::FEV, FiscalEnvironment::HABILITACION));
        $this->assertTrue($this->emitter->canEmit(DocumentType::DEE_POS, FiscalEnvironment::PRODUCTION));
        $this->assertFalse($this->emitter->canEmit('payroll', FiscalEnvironment::HABILITACION));
        $this->assertFalse($this->emitter->canEmit(DocumentType::FEV, 'staging'));
    }

    private function fevContext(
        CompanyFiscalProfile $company,
        DianResolution $resolution,
        ?ElectronicDocumentAcquirer $acquirer,
        array $numbering
    ): array {
        return [
            'company' => $company,
            'document_type' => DocumentType::FEV,
            'environment' => FiscalEnvironment::HABILITACION,
            'numbering' => $numbering,
            'acquirer' => $acquirer,
            'acquirer_id' => $acquirer !== null ? $acquirer->id : null,
            'issued_at' => Carbon::create(2026, 3, 26, 10, 30, 0),
            'currency' => 'COP',
            'lines' => [[
                'sequence' => 1,
                'description' => 'Reserva una noche habitacion 101',
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
            'payment' => [
                'means_code' => '10',
                'terms_code' => '1',
            ],
            'cufe_signing' => [
                'clave_tecnica' => (string) $resolution->technical_key,
            ],
            'source_meta' => [
                'source_type' => 'reservation',
                'source_id' => 1,
            ],
        ];
    }

    private function makeCompany(): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@campoverde.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
    }

    private function makeResolution(CompanyFiscalProfile $company, array $overrides = []): DianResolution
    {
        return DianResolution::create(array_merge([
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
            'technical_key' => null,
            'current_number' => 0,
            'active' => true,
        ], $overrides));
    }

    private function makeAcquirer(): ElectronicDocumentAcquirer
    {
        return ElectronicDocumentAcquirer::create([
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
    }
}
