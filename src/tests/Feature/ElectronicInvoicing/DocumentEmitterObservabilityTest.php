<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator;
use App\Infrastructure\ElectronicInvoicing\Cufe\SoftwareSecurityCodeCalculator;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
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
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Verifies that DocumentEmitter exercises the observability ports: each
 * successful emission MUST end up reflected in the metrics recorder and
 * in the structured logger that the rest of the pipeline can consume.
 */
class DocumentEmitterObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_emission_increments_emitted_total_and_observes_latency(): void
    {
        $metrics = new InMemoryMetricsRecorder();
        $sink = [];
        $logger = $this->makeCapturingLogger($sink);

        $emitter = $this->buildEmitterWith($metrics, $logger);
        [$company, $resolution, $acquirer, $numbering] = $this->seedFiscalContext();

        $document = $emitter->emit($this->fevContext($company, $resolution, $acquirer, $numbering));

        $this->assertSame(DocumentStatus::UBL_BUILT, $document->status);
        $this->assertSame(
            1,
            $metrics->counterCountForLabels('electronic_documents_emitted_total', [
                'type' => DocumentType::FEV,
                'status' => DocumentStatus::UBL_BUILT,
                'environment' => FiscalEnvironment::HABILITACION,
            ])
        );
        $this->assertSame(1, $metrics->histogramSamples('electronic_documents_emission_latency_seconds'));

        $messages = array_column($sink, 'message');
        $this->assertContains('document.queued', $messages);
        $this->assertContains('document.ubl_built', $messages);
    }

    public function test_emission_correlation_id_is_propagated_to_logger(): void
    {
        $metrics = new InMemoryMetricsRecorder();
        $sink = [];
        $logger = $this->makeCapturingLogger($sink);

        $emitter = $this->buildEmitterWith($metrics, $logger);
        [$company, $resolution, $acquirer, $numbering] = $this->seedFiscalContext();

        $context = $this->fevContext($company, $resolution, $acquirer, $numbering);
        $context['correlation_id'] = 'corr-emission-xyz';

        $emitter->emit($context);

        $correlationIds = array_unique(array_filter(array_map(
            fn ($entry) => $entry['context']['correlation_id'] ?? null,
            $sink
        )));

        $this->assertContains('corr-emission-xyz', $correlationIds);
    }

    private function buildEmitterWith(InMemoryMetricsRecorder $metrics, ElectronicInvoicingLogger $logger): DocumentEmitter
    {
        return new DocumentEmitter(
            new DocumentAssembler(),
            new Sha384CufeCalculator(),
            new SoftwareSecurityCodeCalculator(),
            UblBuilderRegistry::default(),
            new InMemoryUnsignedXmlStorage(),
            $metrics,
            $logger
        );
    }

    /**
     * @param  array<int, array{level: string, message: string, context: array<string,mixed>}>  $sink
     */
    private function makeCapturingLogger(array &$sink): ElectronicInvoicingLogger
    {
        $psr = new class($sink) implements LoggerInterface {
            /** @var array<int, array{level: string, message: string, context: array<string,mixed>}> */
            private array $sink;
            public function __construct(array &$sink) { $this->sink = &$sink; }
            public function emergency($message, array $context = []): void { $this->capture('emergency', $message, $context); }
            public function alert($message, array $context = []): void { $this->capture('alert', $message, $context); }
            public function critical($message, array $context = []): void { $this->capture('critical', $message, $context); }
            public function error($message, array $context = []): void { $this->capture('error', $message, $context); }
            public function warning($message, array $context = []): void { $this->capture('warning', $message, $context); }
            public function notice($message, array $context = []): void { $this->capture('notice', $message, $context); }
            public function info($message, array $context = []): void { $this->capture('info', $message, $context); }
            public function debug($message, array $context = []): void { $this->capture('debug', $message, $context); }
            public function log($level, $message, array $context = []): void { $this->capture((string) $level, $message, $context); }
            private function capture(string $level, $message, array $context): void
            {
                $this->sink[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        return new ElectronicInvoicingLogger($psr);
    }

    /**
     * @return array{0: CompanyFiscalProfile, 1: DianResolution, 2: ElectronicDocumentAcquirer, 3: array<string,mixed>}
     */
    private function seedFiscalContext(): array
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
            'country_code' => 'co',
            'email' => 'fiscal@cv.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);

        $resolution = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'resolution_number' => 'OBS-RES-1',
            'resolution_date' => now()->subDay()->toDateString(),
            'prefix' => 'SETP',
            'from_number' => 990000001,
            'to_number' => 990000010,
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
            'valid_from' => now()->subDay()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'active' => true,
            'current_number' => 0,
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

        return [$company, $resolution, $acquirer, $numbering];
    }

    private function fevContext(
        CompanyFiscalProfile $company,
        DianResolution $resolution,
        ElectronicDocumentAcquirer $acquirer,
        array $numbering
    ): array {
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
    }
}
