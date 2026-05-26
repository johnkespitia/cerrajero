<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator;
use App\Infrastructure\ElectronicInvoicing\Cufe\SoftwareSecurityCodeCalculator;
use App\Infrastructure\ElectronicInvoicing\Secrets\ArraySecretManager;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\DianSoftwareCredential;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentAcquirer;
use App\Services\ElectronicInvoicing\CreditDebitNoteService;
use App\Services\ElectronicInvoicing\DocumentAssembler;
use App\Services\ElectronicInvoicing\DocumentEmitter;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteInvalidPayloadException;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteUnavailableException;
use App\Services\ElectronicInvoicing\NumberingAllocator;
use App\Services\ElectronicInvoicing\Storage\InMemoryUnsignedXmlStorage;
use App\Services\ElectronicInvoicing\UblBuilderRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditDebitNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var InMemoryUnsignedXmlStorage */
    private $storage;

    /** @var DocumentEmitter */
    private $emitter;

    /** @var CreditDebitNoteService */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['electronic-invoicing.enabled' => true]);
        config(['electronic-invoicing.environment' => FiscalEnvironment::HABILITACION]);

        $secrets = new ArraySecretManager(['hab/pin' => 'TEST-PIN-HAB']);
        $this->app->instance(SecretManagerInterface::class, $secrets);

        $this->storage = new InMemoryUnsignedXmlStorage();
        $this->emitter = new DocumentEmitter(
            new DocumentAssembler(),
            new Sha384CufeCalculator(),
            new SoftwareSecurityCodeCalculator(),
            UblBuilderRegistry::default(),
            $this->storage
        );
        $this->service = new CreditDebitNoteService(
            new NumberingAllocator(),
            $this->emitter,
            null,
            $secrets
        );
    }

    public function test_emits_credit_note_referencing_parent_fev(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::NC, [
            'prefix' => 'NCR',
            'from_number' => 1,
            'to_number' => 1000,
        ]);

        $document = $this->service->emitCreditNote($parent->id, [
            'discrepancy_code' => '02',
            'reason' => 'Anulación factura completa',
            'lines' => [[
                'description' => 'Devolución hospedaje 1 noche',
                'quantity' => 1,
                'unit_price' => 100000,
                'line_total' => 100000,
                'tax_amount' => 19000,
                'taxable_amount' => 100000,
                'tax_percent' => '19.00',
            ]],
        ]);

        $this->assertSame(DocumentType::NC, $document->document_type);
        $this->assertSame(DocumentStatus::UBL_BUILT, $document->status);
        $this->assertSame($parent->id, (int) $document->references_document_id);
        $this->assertSame('credit_note', $document->source_type);
        $this->assertSame($parent->id, (int) $document->source_id);
        $this->assertSame('NCR1', $document->dian_number);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', (string) $document->cufe_cude);

        $xml = $this->storage->retrieve((string) $document->xml_unsigned_path);
        $this->assertNotNull($xml);
        $this->assertStringContainsString('<CreditNote', $xml);
        $this->assertStringContainsString('<cbc:UUID', $xml);
        $this->assertStringContainsString((string) $parent->cufe_cude, $xml);
        $this->assertMatchesRegularExpression('/<cbc:ResponseCode[^>]*>02<\/cbc:ResponseCode>/', $xml);
        $this->assertMatchesRegularExpression('/<cbc:Description[^>]*>Anulación factura completa<\/cbc:Description>/', $xml);
    }

    public function test_emits_debit_note_referencing_parent_fev(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::ND, [
            'prefix' => 'NDR',
            'from_number' => 1,
            'to_number' => 1000,
        ]);

        $document = $this->service->emitDebitNote($parent->id, [
            'reason' => 'Recargo por mora',
            'lines' => [[
                'description' => 'Intereses moratorios',
                'quantity' => 1,
                'unit_price' => 5000,
                'line_total' => 5000,
            ]],
        ]);

        $this->assertSame(DocumentType::ND, $document->document_type);
        $this->assertSame(DocumentStatus::UBL_BUILT, $document->status);
        $this->assertSame($parent->id, (int) $document->references_document_id);
        $this->assertSame('debit_note', $document->source_type);
        $this->assertSame('NDR1', $document->dian_number);

        $xml = $this->storage->retrieve((string) $document->xml_unsigned_path);
        $this->assertNotNull($xml);
        $this->assertStringContainsString('<DebitNote', $xml);
        $this->assertStringContainsString((string) $parent->cufe_cude, $xml);
    }

    public function test_rejects_when_parent_does_not_exist(): void
    {
        $this->seedFiscalConfiguration();

        $this->expectException(CreditDebitNoteInvalidPayloadException::class);
        $this->expectExceptionMessage('Parent ElectronicDocument #9999 not found.');
        $this->service->emitCreditNote(9999, [
            'discrepancy_code' => '02',
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);
    }

    public function test_rejects_when_parent_type_is_not_referenceable(): void
    {
        [$company, $parent] = $this->seedParentFev();
        // Cambiar a tipo NC para forzar el rechazo.
        $parent->document_type = DocumentType::NC;
        $parent->save();

        $this->seedResolution($company, DocumentType::NC, [
            'prefix' => 'NCR',
            'from_number' => 1,
            'to_number' => 1000,
        ]);

        $this->expectException(CreditDebitNoteInvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot derive NC/ND from a document of type "nc"');
        $this->service->emitCreditNote($parent->id, [
            'discrepancy_code' => '02',
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);
    }

    public function test_rejects_when_parent_status_is_not_referenceable(): void
    {
        [, $parent] = $this->seedParentFev();
        $parent->status = DocumentStatus::DRAFT;
        $parent->save();

        $this->expectException(CreditDebitNoteInvalidPayloadException::class);
        $this->expectExceptionMessage('status "draft" which is not referenceable');
        $this->service->emitCreditNote($parent->id, [
            'discrepancy_code' => '02',
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);
    }

    public function test_rejects_credit_note_without_discrepancy_code(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::NC, ['prefix' => 'NCR', 'from_number' => 1, 'to_number' => 100]);

        $this->expectException(CreditDebitNoteInvalidPayloadException::class);
        $this->expectExceptionMessage('discrepancy_code is required for NC');
        $this->service->emitCreditNote($parent->id, [
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);
    }

    public function test_rejects_debit_note_without_reason(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::ND, ['prefix' => 'NDR', 'from_number' => 1, 'to_number' => 100]);

        $this->expectException(CreditDebitNoteInvalidPayloadException::class);
        $this->expectExceptionMessage('reason is required for ND');
        $this->service->emitDebitNote($parent->id, [
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);
    }

    public function test_rejects_invalid_lines(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::NC, ['prefix' => 'NCR', 'from_number' => 1, 'to_number' => 100]);

        $this->expectException(CreditDebitNoteInvalidPayloadException::class);
        $this->expectExceptionMessageMatches('/lines.*positive|require at least one line/i');
        $this->service->emitCreditNote($parent->id, [
            'discrepancy_code' => '02',
            'lines' => [['description' => 'bad', 'quantity' => 0, 'unit_price' => 0, 'line_total' => 0]],
        ]);
    }

    public function test_resolution_missing_raises_unavailable(): void
    {
        [, $parent] = $this->seedParentFev();
        // No NC resolution seeded on purpose.

        $this->expectException(CreditDebitNoteUnavailableException::class);
        $this->expectExceptionMessage('No active DianResolution for environment "habilitacion" and type "nc"');
        $this->service->emitCreditNote($parent->id, [
            'discrepancy_code' => '02',
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);
    }

    public function test_credit_note_endpoint_returns_electronic_document(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::NC, ['prefix' => 'NCR', 'from_number' => 1, 'to_number' => 100]);

        $user = \App\Models\User::create([
            'name' => 'Fiscal',
            'email' => 'fiscal@test.local',
            'password' => bcrypt('test1234'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/electronic-invoicing/documents/{$parent->id}/credit-note", [
            'discrepancy_code' => '02',
            'reason' => 'Anulación factura',
            'lines' => [[
                'description' => 'Devolución hospedaje',
                'quantity' => 1,
                'unit_price' => 100000,
                'line_total' => 100000,
                'tax_amount' => 19000,
                'taxable_amount' => 100000,
                'tax_percent' => '19.00',
            ]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('electronic_document.document_type', DocumentType::NC)
            ->assertJsonPath('electronic_document.status', DocumentStatus::UBL_BUILT)
            ->assertJsonPath('electronic_document.references_document_id', $parent->id)
            ->assertJsonPath('electronic_document.dian_number', 'NCR1');
    }

    public function test_credit_note_endpoint_returns_422_when_discrepancy_code_missing(): void
    {
        [$company, $parent] = $this->seedParentFev();
        $this->seedResolution($company, DocumentType::NC, ['prefix' => 'NCR', 'from_number' => 1, 'to_number' => 100]);

        $user = \App\Models\User::create([
            'name' => 'Fiscal',
            'email' => 'fiscal@test.local',
            'password' => bcrypt('test1234'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/electronic-invoicing/documents/{$parent->id}/credit-note", [
            'reason' => 'Sin código',
            'lines' => [[
                'description' => 'x',
                'quantity' => 1,
                'unit_price' => 1000,
                'line_total' => 1000,
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath(
                'electronic_document_error.code',
                CreditDebitNoteInvalidPayloadException::CODE_DISCREPANCY_CODE_REQUIRED
            );
    }

    public function test_credit_note_endpoint_returns_422_when_parent_not_found(): void
    {
        $this->seedFiscalConfiguration();
        $user = \App\Models\User::create([
            'name' => 'Fiscal',
            'email' => 'fiscal@test.local',
            'password' => bcrypt('test1234'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/electronic-invoicing/documents/9999/credit-note', [
            'discrepancy_code' => '02',
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath(
                'electronic_document_error.code',
                CreditDebitNoteInvalidPayloadException::CODE_PARENT_NOT_FOUND
            );
    }

    private function seedParentFev(): array
    {
        [$company, $resolution, $acquirer] = $this->seedFiscalConfiguration();

        $numbering = (new NumberingAllocator())->allocate(
            $company->id,
            FiscalEnvironment::HABILITACION,
            DocumentType::FEV
        );

        $context = [
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
                'description' => 'Hospedaje 1 noche',
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

        $parent = $this->emitter->emit($context);
        return [$company, $parent];
    }

    private function seedFiscalConfiguration(): array
    {
        $company = CompanyFiscalProfile::create([
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

        $resolution = $this->seedResolution($company, DocumentType::FEV, [
            'prefix' => 'SETP',
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
            'from_number' => 990000001,
            'to_number' => 990010000,
        ]);

        DianSoftwareCredential::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'software_pin_secret_ref' => 'ref:hab/pin',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
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

        return [$company, $resolution, $acquirer];
    }

    private function seedResolution(CompanyFiscalProfile $company, string $documentType, array $overrides = []): DianResolution
    {
        return DianResolution::create(array_merge([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => $documentType,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 1,
            'to_number' => 1000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'technical_key' => null,
            'current_number' => 0,
            'active' => true,
        ], $overrides));
    }
}
