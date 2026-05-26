<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\Contingency\ContingencyManager;
use App\Services\ElectronicInvoicing\Contingency\InMemoryCircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContingencyManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_emit_in_contingency_follows_breaker_state(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 2);
        $manager = new ContingencyManager($breaker);

        $this->assertFalse($manager->shouldEmitInContingency());
        $manager->recordDispatchFailure();
        $manager->recordDispatchFailure();
        $this->assertTrue($manager->shouldEmitInContingency());
    }

    public function test_record_success_resets_breaker(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 1);
        $manager = new ContingencyManager($breaker);
        $manager->recordDispatchFailure();
        $this->assertTrue($manager->shouldEmitInContingency());
        $manager->recordDispatchSuccess();
        $this->assertFalse($manager->shouldEmitInContingency());
    }

    public function test_mark_contingency_document_transitions_and_persists_metadata(): void
    {
        $document = $this->seedDraftDocument();
        $manager = new ContingencyManager(new InMemoryCircuitBreaker());

        $persisted = $manager->markContingencyDocument($document, 'dian_unreachable');

        $this->assertSame(DocumentStatus::CONTINGENCY_EMITTED, $persisted->status);
        $this->assertTrue((bool) $persisted->contingency);
        $this->assertSame('dian_unreachable', $persisted->contingency_reason);
        $this->assertNotNull($persisted->contingency_emitted_at);
        $events = $persisted->events()->pluck('event_type')->all();
        $this->assertContains('contingency_emitted', $events);
    }

    public function test_mark_contingency_skips_state_transition_for_non_draft_documents(): void
    {
        $document = $this->seedDraftDocument();
        $document->status = DocumentStatus::UBL_BUILT;
        $document->save();

        $manager = new ContingencyManager(new InMemoryCircuitBreaker());
        $persisted = $manager->markContingencyDocument($document);
        $this->assertSame(DocumentStatus::UBL_BUILT, $persisted->status);
        $this->assertTrue((bool) $persisted->contingency);
    }

    private function seedDraftDocument(): ElectronicDocument
    {
        $company = CompanyFiscalProfile::create([
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
            'status' => DocumentStatus::DRAFT,
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
