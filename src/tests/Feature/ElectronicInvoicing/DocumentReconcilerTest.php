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
use App\Services\ElectronicInvoicing\DocumentReconciler;
use App\Services\ElectronicInvoicing\Storage\InMemoryDianResponseStorage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\TestCase;

class DocumentReconcilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_track_received_to_accepted(): void
    {
        $document = $this->seedDocument(DocumentStatus::DIAN_TRACK_RECEIVED, ['dian_track_id' => 'TRK-1']);
        [$reconciler, $soap, $responseStorage] = $this->buildReconciler();
        $soap->script('getStatusZip', [
            'result' => [
                'IsValid' => 'true',
                'StatusCode' => '00',
                'XmlBytes' => base64_encode('<AR/>'),
            ],
        ]);

        $summary = $reconciler->reconcilePending();
        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['accepted']);

        $document->refresh();
        $this->assertSame(DocumentStatus::DIAN_ACCEPTED, $document->status);
        $this->assertNotNull($document->dian_application_response_path);
        $this->assertSame('<AR/>', $responseStorage->retrieve($document->dian_application_response_path));
        $this->assertContains('dian_accepted', $document->events()->pluck('event_type')->all());
    }

    public function test_reconcile_track_received_to_rejected_terminal(): void
    {
        $document = $this->seedDocument(DocumentStatus::DIAN_TRACK_RECEIVED, ['dian_track_id' => 'TRK-2']);
        [$reconciler, $soap] = $this->buildReconciler();
        $soap->script('getStatusZip', [
            'result' => [
                'IsValid' => 'false',
                'StatusCode' => '99',
                'ErrorMessage' => [['code' => 'FAD06', 'message' => 'Firma invalida']],
            ],
        ]);

        $summary = $reconciler->reconcilePending();
        $this->assertSame(1, $summary['rejected']);
        $document->refresh();
        $this->assertSame(DocumentStatus::DIAN_REJECTED_TERMINAL, $document->status);
    }

    public function test_reconcile_sent_to_dian_without_trackid_marks_validating_and_counts_as_stuck(): void
    {
        $document = $this->seedDocument(DocumentStatus::SENT_TO_DIAN, []);
        [$reconciler] = $this->buildReconciler();

        $summary = $reconciler->reconcilePending();
        $this->assertSame(1, $summary['stuck']);
        $document->refresh();
        $this->assertSame(DocumentStatus::DIAN_VALIDATING, $document->status);
        $this->assertContains('status_polled', $document->events()->pluck('event_type')->all());
    }

    public function test_reconcile_dead_letters_documents_older_than_window(): void
    {
        $document = $this->seedDocument(DocumentStatus::SENT_TO_DIAN, [
            'last_attempt_at' => Carbon::now()->subHours(3),
        ]);
        [$reconciler] = $this->buildReconciler(stuckAfterMinutes: 10);

        $summary = $reconciler->reconcilePending();
        $this->assertSame(1, $summary['deadLettered']);
        $document->refresh();
        $this->assertSame(DocumentStatus::DEAD_LETTER, $document->status);
    }

    public function test_reconcile_keeps_document_pending_when_dian_still_processing(): void
    {
        $document = $this->seedDocument(DocumentStatus::DIAN_TRACK_RECEIVED, ['dian_track_id' => 'TRK-3']);
        [$reconciler, $soap] = $this->buildReconciler();
        $soap->script('getStatusZip', [
            'result' => ['SomethingElse' => 'still-processing'],
        ]);

        $summary = $reconciler->reconcilePending();
        $this->assertSame(0, $summary['accepted']);
        $this->assertSame(0, $summary['rejected']);
        $document->refresh();
        $this->assertSame(DocumentStatus::DIAN_VALIDATING, $document->status);
    }

    public function test_reconcile_soap_error_records_breaker_failure_and_counts_as_error(): void
    {
        $document = $this->seedDocument(DocumentStatus::DIAN_TRACK_RECEIVED, ['dian_track_id' => 'TRK-4']);
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 1);
        $contingency = new ContingencyManager($breaker);
        [$reconciler, $soap] = $this->buildReconciler(contingencyManager: $contingency);
        $soap->fail('getStatusZip', new \RuntimeException('connection refused'));

        $summary = $reconciler->reconcilePending();
        $this->assertSame(1, $summary['errors']);
        $this->assertTrue($contingency->shouldEmitInContingency());
        $document->refresh();
        $this->assertContains('error', $document->events()->pluck('event_type')->all());
    }

    /**
     * @return array{0: DocumentReconciler, 1: FakeDianSoapClient, 2: InMemoryDianResponseStorage}
     */
    private function buildReconciler(?ContingencyManager $contingencyManager = null, int $stuckAfterMinutes = 10): array
    {
        $soap = new FakeDianSoapClient();
        $responseStorage = new InMemoryDianResponseStorage();
        $contingency = $contingencyManager ?? new ContingencyManager(new InMemoryCircuitBreaker());
        $reconciler = new DocumentReconciler(
            $soap,
            $responseStorage,
            new \App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper(),
            $contingency,
            new \App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder(),
            new \App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger(),
            5,
            $stuckAfterMinutes
        );

        return [$reconciler, $soap, $responseStorage];
    }

    private function seedDocument(string $status, array $extra): ElectronicDocument
    {
        $company = CompanyFiscalProfile::firstOrCreate(
            ['nit' => '900123456'],
            [
                'legal_name' => 'CV', 'trade_name' => 'CV', 'dv' => 1,
                'tax_regime_code' => '48', 'tax_responsibilities' => ['O-13'],
                'address_line' => 'Km 5', 'city_code_dian' => '63190',
                'country_code' => 'CO', 'email' => 'fiscal@cv.local',
                'environment' => FiscalEnvironment::HABILITACION, 'active' => true,
            ]
        );
        $resolution = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP', 'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000001, 'to_number' => 990010000,
            'valid_from' => '2026-01-01', 'valid_to' => '2099-12-31',
            'current_number' => 0, 'active' => true,
        ]);
        return ElectronicDocument::create(array_merge([
            'company_id' => $company->id,
            'resolution_id' => $resolution->id,
            'document_type' => DocumentType::FEV,
            'reference_code' => 'ref-' . bin2hex(random_bytes(4)),
            'dian_number' => '990000001',
            'status' => $status,
            'environment' => FiscalEnvironment::HABILITACION,
            'subtotal' => '100000.00', 'total_taxes' => '19000.00', 'total' => '119000.00',
            'currency_code' => 'COP', 'issue_date' => now(),
            'source_type' => 'reservation', 'source_id' => 1,
        ], $extra));
    }
}
