<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Services\ElectronicInvoicing\Dispatch\DianResponseMapper;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates a single execution of the DIAN habilitacion test set.
 *
 * Pipeline per case:
 *  1. `TestCaseEmitterInterface::emit` produces the signed UBL XML.
 *  2. Wraps it in a DIAN-compliant ZIP envelope (BASE64) - the packager
 *     used during the production dispatcher is intentionally NOT
 *     coupled here so the runner can be exercised against synthetic
 *     payloads without touching `ElectronicDocument`. The ZIP is built
 *     inline with `ZipArchive`.
 *  3. Calls `DianSoapClientInterface::sendTestSetAsync($fileName,
 *     $contentFile, $testSetId)`.
 *  4. Maps the SOAP response through `DianResponseMapper::map()` (sync
 *     semantics so we can read `is_valid` immediately) and compares
 *     against the case `expectations`.
 *  5. Accumulates a row per case + aggregated counters.
 *
 * The runner never raises: an emitter / SOAP error is captured per
 * case with `status='error'`, the global report still completes so the
 * operator can fix issues incrementally.
 *
 * Returns a `TestSetReport` ready to serialise to JSON / Markdown.
 */
class HabilitacionTestSetRunner
{
    public function __construct(
        private readonly TestCaseEmitterInterface $emitter,
        private readonly DianSoapClientInterface $soapClient,
        private readonly DianResponseMapper $responseMapper = new DianResponseMapper(),
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
        private readonly string $environment = 'habilitacion',
    ) {
    }

    /**
     * @param TestCaseDescriptor[] $cases
     */
    public function run(array $cases, string $testSetId): TestSetReport
    {
        $rows = [];
        $accepted = 0;
        $rejected = 0;
        $errors = 0;
        $expectationFailures = 0;
        $correlationId = (string) Str::uuid();
        $logger = $this->logger->withCorrelationId($correlationId);

        $logger->info('habilitacion.test_set_started', [
            'cases' => count($cases),
            'environment' => $this->environment,
            'test_set_id_hint' => substr($testSetId, 0, 8),
        ]);

        foreach ($cases as $case) {
            $row = $this->runOne($case, $testSetId);
            $rows[] = $row;
            switch ($row['status']) {
                case 'accepted':
                    $accepted++;
                    break;
                case 'rejected':
                    $rejected++;
                    break;
                case 'failed_expectation':
                    $expectationFailures++;
                    break;
                case 'error':
                    $errors++;
                    break;
            }
        }

        $total = count($cases);
        $report = new TestSetReport([
            'environment' => $this->environment,
            'test_set_id' => $testSetId,
            'generated_at' => date('c'),
            'total' => $total,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'errors' => $errors,
            'expectation_failures' => $expectationFailures,
            'acceptance_rate' => $total === 0 ? 0.0 : round(($accepted / $total) * 100, 2),
            'cases' => $rows,
        ]);

        $this->metrics->setGauge('electronic_documents_habilitacion_acceptance', $report->acceptanceRate(), [
            'environment' => $this->environment,
        ]);
        $logger->info('habilitacion.test_set_completed', [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'errors' => $errors,
            'acceptance_rate' => $report->acceptanceRate(),
        ]);

        return $report;
    }

    private function runOne(TestCaseDescriptor $case, string $testSetId): array
    {
        $startedAt = microtime(true);
        try {
            $emitted = $this->emitter->emit($case);
        } catch (Throwable $e) {
            return $this->buildRow($case, 'error', null, ['reason' => 'emit_failed', 'message' => $e->getMessage()], $startedAt);
        }
        $signedXml = (string) ($emitted['signed_xml'] ?? '');
        $fileNameXml = (string) ($emitted['file_name'] ?? ($case->code . '.xml'));
        if ($signedXml === '') {
            return $this->buildRow($case, 'error', null, ['reason' => 'empty_signed_xml'], $startedAt);
        }

        try {
            $zip = $this->packageZip($fileNameXml, $signedXml);
        } catch (Throwable $e) {
            return $this->buildRow($case, 'error', null, ['reason' => 'zip_failed', 'message' => $e->getMessage()], $startedAt);
        }

        try {
            $response = $this->soapClient->sendTestSetAsync(
                pathinfo($fileNameXml, PATHINFO_FILENAME) . '.zip',
                base64_encode($zip),
                $testSetId
            );
        } catch (Throwable $e) {
            return $this->buildRow($case, 'error', null, ['reason' => 'soap_failed', 'message' => $e->getMessage()], $startedAt);
        }

        $outcome = $this->responseMapper->map($response, 'SendBillSync');
        $expected = (string) ($case->expectations['target_status'] ?? 'dian_accepted');
        $actual = (string) $outcome['target_status'];
        $status = $outcome['is_valid'] === true ? 'accepted' : 'rejected';

        if ($expected !== $actual) {
            $status = 'failed_expectation';
        }

        return $this->buildRow($case, $status, $outcome, [
            'expected' => $expected,
            'actual' => $actual,
        ], $startedAt);
    }

    private function buildRow(
        TestCaseDescriptor $case,
        string $status,
        ?array $outcome,
        array $diagnostics,
        float $startedAt
    ): array {
        return [
            'code' => $case->code,
            'category' => $case->category,
            'description' => $case->description,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'dian_status_code' => $outcome['status_code'] ?? null,
            'dian_is_valid' => $outcome['is_valid'] ?? null,
            'dian_track_id' => $outcome['track_id'] ?? null,
            'errors' => $outcome['structured_errors'] ?? [],
            'diagnostics' => $diagnostics,
        ];
    }

    private function packageZip(string $fileNameXml, string $signedXml): string
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'habset_');
        try {
            $archive = new \ZipArchive();
            if ($archive->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot open temporary zip for the habilitacion test set.');
            }
            $archive->addFromString($fileNameXml, $signedXml);
            $archive->close();
            $bytes = file_get_contents($tempZip);
            if ($bytes === false) {
                throw new \RuntimeException('Failed to read the temporary zip for the habilitacion test set.');
            }
            return $bytes;
        } finally {
            @unlink($tempZip);
        }
    }
}
