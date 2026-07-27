<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Services\ElectronicInvoicing\Habilitacion\HabilitacionTestSetRunner;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseDescriptor;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseEmitterInterface;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseRepository;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;

class HabilitacionTestSetRunnerTest extends TestCase
{
    public function test_runner_summarises_accepted_rejected_and_errors_per_case(): void
    {
        $emitter = $this->stubEmitter();
        $soap = new FakeDianSoapClient();
        $soap->scriptQueue('sendTestSetAsync', [
            ['result' => ['IsValid' => 'true', 'StatusCode' => '00']],
            ['result' => ['IsValid' => 'false', 'StatusCode' => '99', 'ErrorMessage' => [['code' => 'X1', 'message' => 'XSD']]]],
            ['result' => ['IsValid' => 'true', 'StatusCode' => '00']],
        ]);

        $runner = new HabilitacionTestSetRunner($emitter, $soap);
        // The middle case is expected to be rejected by DIAN (negative
        // path under test), so we declare it explicitly in expectations
        // and the runner counts it as "rejected" rather than as an
        // expectation failure.
        $report = $runner->run([
            new TestCaseDescriptor('FEV-01', 'fev', 'baseline', ['type' => 'fev']),
            new TestCaseDescriptor('NEG-01', 'fev', 'rechazo terminal esperado', ['type' => 'fev'], ['target_status' => 'dian_rejected_terminal']),
            new TestCaseDescriptor('NC-01', 'nc', 'devolucion', ['type' => 'nc']),
        ], 'set-id-1');

        $this->assertSame(3, $report->total());
        $this->assertSame(2, $report->accepted());
        $this->assertSame(1, $report->rejected());
        $this->assertSame(0, $report->errors());
        $this->assertEqualsWithDelta(66.67, $report->acceptanceRate(), 0.01);
        $this->assertFalse($report->isCutoverReady());
    }

    public function test_runner_marks_cutover_ready_when_all_accepted(): void
    {
        $emitter = $this->stubEmitter();
        $soap = new FakeDianSoapClient();
        $soap->scriptQueue('sendTestSetAsync', [
            ['result' => ['IsValid' => 'true', 'StatusCode' => '00']],
            ['result' => ['IsValid' => 'true', 'StatusCode' => '00']],
        ]);

        $runner = new HabilitacionTestSetRunner($emitter, $soap);
        $report = $runner->run([
            new TestCaseDescriptor('FEV-01', 'fev', 'baseline', []),
            new TestCaseDescriptor('FEV-02', 'fev', 'descuento', []),
        ], 'set-id-2');

        $this->assertTrue($report->isCutoverReady());
        $this->assertSame(100.0, $report->acceptanceRate());
    }

    public function test_runner_flags_soap_failures_as_error_without_aborting(): void
    {
        $emitter = $this->stubEmitter();
        $soap = new FakeDianSoapClient();
        $soap->fail('sendTestSetAsync', new \RuntimeException('connection refused'));

        $runner = new HabilitacionTestSetRunner($emitter, $soap);
        $report = $runner->run([
            new TestCaseDescriptor('FEV-01', 'fev', 'baseline', []),
        ], 'set-id-3');

        $this->assertSame(1, $report->errors());
        $this->assertSame(0, $report->accepted());
        $cases = $report->payload()['cases'];
        $this->assertSame('error', $cases[0]['status']);
        $this->assertSame('soap_failed', $cases[0]['diagnostics']['reason']);
    }

    public function test_runner_records_failed_expectation_when_outcome_differs(): void
    {
        $emitter = $this->stubEmitter();
        $soap = new FakeDianSoapClient();
        // Expected dian_accepted but DIAN replies recoverable.
        $soap->scriptQueue('sendTestSetAsync', [
            ['result' => ['IsValid' => 'false', 'StatusCode' => '89']],
        ]);
        $runner = new HabilitacionTestSetRunner($emitter, $soap);
        $report = $runner->run([
            new TestCaseDescriptor('FEV-01', 'fev', 'baseline', [], ['target_status' => 'dian_accepted']),
        ], 'set-id-4');
        $this->assertSame(1, $report->expectationFailures());
        $this->assertSame(0, $report->rejected());
    }

    public function test_report_serialises_to_markdown_and_json(): void
    {
        $emitter = $this->stubEmitter();
        $soap = new FakeDianSoapClient();
        $soap->scriptQueue('sendTestSetAsync', [
            ['result' => ['IsValid' => 'true', 'StatusCode' => '00']],
        ]);
        $runner = new HabilitacionTestSetRunner($emitter, $soap);
        $report = $runner->run([
            new TestCaseDescriptor('FEV-01', 'fev', 'baseline', []),
        ], 'set-id-5');

        $md = $report->toMarkdown();
        $this->assertStringContainsString('Reporte set de pruebas DIAN', $md);
        $this->assertStringContainsString('FEV-01', $md);

        $json = json_decode($report->toJson(), true);
        $this->assertSame(1, $json['total']);
        $this->assertSame('set-id-5', $json['test_set_id']);
    }

    public function test_canonical_repository_returns_a_balanced_pack(): void
    {
        $cases = TestCaseRepository::canonical();
        $this->assertNotEmpty($cases);
        $categories = array_count_values(array_map(fn ($c) => $c->category, $cases));
        $this->assertArrayHasKey('fev', $categories);
        $this->assertArrayHasKey('nc', $categories);
        $this->assertArrayHasKey('nd', $categories);
    }

    private function stubEmitter(): TestCaseEmitterInterface
    {
        return new class implements TestCaseEmitterInterface {
            public function emit(TestCaseDescriptor $case): array
            {
                return [
                    'file_name' => $case->code . '.xml',
                    'signed_xml' => '<Invoice><cbc:ID>' . $case->code . '</cbc:ID></Invoice>',
                    'dian_number' => $case->code,
                ];
            }
        };
    }
}
