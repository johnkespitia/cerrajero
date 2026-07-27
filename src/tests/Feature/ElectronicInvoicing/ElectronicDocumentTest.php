<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\ElectronicDocumentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ElectronicDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_persist_initial_draft_for_each_document_type(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();

        foreach (DocumentType::all() as $type) {
            $doc = ElectronicDocument::create($this->payload($company->id, $resolution->id, [
                'document_type' => $type,
                'reference_code' => 'REF-' . $type,
            ]));

            $this->assertNotNull($doc->id);
            $this->assertSame(DocumentStatus::DRAFT, $doc->status);
            $this->assertSame($type, $doc->document_type);
            $this->assertFalse($doc->isTerminal());
            $this->assertTrue($doc->isInitial());
        }
    }

    public function test_reference_code_is_unique_per_company_and_type(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();

        ElectronicDocument::create($this->payload($company->id, $resolution->id));

        $this->expectException(QueryException::class);
        ElectronicDocument::create($this->payload($company->id, $resolution->id));
    }

    public function test_setting_invalid_status_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $doc = new ElectronicDocument();
        $doc->status = 'half-signed';
    }

    public function test_setting_invalid_document_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $doc = new ElectronicDocument();
        $doc->document_type = 'payroll';
    }

    public function test_setting_invalid_environment_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $doc = new ElectronicDocument();
        $doc->environment = 'staging';
    }

    public function test_transition_to_accepted_via_sync_path(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();
        $doc = ElectronicDocument::create($this->payload($company->id, $resolution->id));

        $doc->transitionTo(DocumentStatus::UBL_BUILT);
        $doc->transitionTo(DocumentStatus::XADES_SIGNED);
        $doc->transitionTo(DocumentStatus::SENT_TO_DIAN);
        $doc->transitionTo(DocumentStatus::DIAN_ACCEPTED);
        $doc->save();

        $this->assertSame(DocumentStatus::DIAN_ACCEPTED, $doc->fresh()->status);
        $this->assertTrue($doc->isTerminal());
    }

    public function test_invalid_transition_is_blocked(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();
        $doc = ElectronicDocument::create($this->payload($company->id, $resolution->id));

        $this->expectException(InvalidArgumentException::class);
        $doc->transitionTo(DocumentStatus::DIAN_ACCEPTED);
    }

    public function test_credit_note_can_reference_an_accepted_document(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();
        $original = ElectronicDocument::create($this->payload($company->id, $resolution->id, [
            'reference_code' => 'FEV-1',
        ]));

        $nc = new ElectronicDocument($this->payload($company->id, $resolution->id, [
            'document_type' => DocumentType::NC,
            'reference_code' => 'NC-1',
        ]));

        $nc->assertCanReference($original);
        $nc->references_document_id = $original->id;
        $nc->save();

        $this->assertSame($original->id, $nc->referencesDocument->id);
        $this->assertCount(1, $original->references);
    }

    public function test_only_nc_or_nd_can_reference_another_document(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();
        $original = ElectronicDocument::create($this->payload($company->id, $resolution->id, [
            'reference_code' => 'FEV-1',
        ]));
        $other = new ElectronicDocument($this->payload($company->id, $resolution->id, [
            'document_type' => DocumentType::FEV,
            'reference_code' => 'FEV-2',
        ]));

        $this->expectException(InvalidArgumentException::class);
        $other->assertCanReference($original);
    }

    public function test_events_are_persisted_and_linked_to_document(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();
        $doc = ElectronicDocument::create($this->payload($company->id, $resolution->id));

        ElectronicDocumentEvent::create([
            'electronic_document_id' => $doc->id,
            'event_type' => 'queued',
            'payload' => ['source' => 'kiosk_invoice'],
            'actor' => 'system',
            'correlation_id' => '00000000-0000-0000-0000-000000000abc',
            'occurred_at' => now(),
        ]);

        $this->assertCount(1, $doc->events);
        $this->assertSame('queued', $doc->events->first()->event_type);
        $this->assertSame('kiosk_invoice', $doc->events->first()->payload['source']);
    }

    public function test_acquirer_can_be_attached(): void
    {
        [$company, $resolution] = $this->makeCompanyAndResolution();
        $acquirer = ElectronicDocumentAcquirer::create([
            'document_type' => 'nit',
            'document_number' => '800111222',
            'dv' => 3,
            'legal_name' => 'Cliente B2B SAS',
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'country_code' => 'CO',
        ]);

        $doc = ElectronicDocument::create($this->payload($company->id, $resolution->id, [
            'acquirer_id' => $acquirer->id,
        ]));

        $this->assertSame($acquirer->id, $doc->acquirer->id);
        $this->assertCount(1, $acquirer->electronicDocuments);
    }

    private function makeCompanyAndResolution(): array
    {
        $company = CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
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
        $resolution = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000000,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2027-01-01',
            'technical_key' => null,
            'current_number' => 990000000,
            'active' => true,
        ]);

        return [$company, $resolution];
    }

    private function payload(int $companyId, int $resolutionId, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $companyId,
            'resolution_id' => $resolutionId,
            'document_type' => DocumentType::FEV,
            'reference_code' => 'REF-0001',
            'status' => DocumentStatus::DRAFT,
            'environment' => FiscalEnvironment::HABILITACION,
            'subtotal' => '0.00',
            'total_taxes' => '0.00',
            'total' => '0.00',
            'currency_code' => 'COP',
            'issue_date' => now(),
            'source_type' => 'kiosk_invoice',
            'source_id' => 1,
        ], $overrides);
    }
}
