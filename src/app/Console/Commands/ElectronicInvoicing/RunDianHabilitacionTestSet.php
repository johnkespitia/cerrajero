<?php

namespace App\Console\Commands\ElectronicInvoicing;

use App\Services\ElectronicInvoicing\Habilitacion\HabilitacionTestSetRunner;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseRepository;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Illuminate\Console\Command;

/**
 * Executes the DIAN habilitacion test set against HAB and writes a
 * structured report to disk (one JSON per run plus `latest.json`).
 *
 * Usage:
 *  - `php artisan electronic-invoicing:run-test-set` -> canonical pack.
 *  - `php artisan electronic-invoicing:run-test-set --fixtures=path.json`
 *    -> custom pack (used by operations to inject regression cases).
 *  - `php artisan electronic-invoicing:run-test-set --test-set-id=...`
 *    -> override the TestSetId obtained from `DianSoftwareCredential`.
 */
class RunDianHabilitacionTestSet extends Command
{
    protected $signature = 'electronic-invoicing:run-test-set
        {--fixtures= : Path to a JSON file with custom test cases}
        {--test-set-id= : Override the TestSetId stored in DianSoftwareCredential}
        {--format=markdown : Output format: markdown or json}';

    protected $description = 'Run the DIAN habilitacion test set against HAB and persist the report.';

    public function handle(HabilitacionTestSetRunner $runner, TestSetReportRepository $reports): int
    {
        $cases = $this->loadCases();
        if ($cases === []) {
            $this->error('No test cases available. Aborting.');
            return Command::FAILURE;
        }

        $testSetId = (string) ($this->option('test-set-id') ?: config('electronic-invoicing.test_set_id'));
        if ($testSetId === '') {
            $this->error('Missing TestSetId. Configure DIAN_TEST_SET_ID or pass --test-set-id=...');
            return Command::FAILURE;
        }

        $this->line(sprintf('Running %d cases against %s...', count($cases), $testSetId));
        $report = $runner->run($cases, $testSetId);
        $path = $reports->save($report);

        $format = (string) $this->option('format');
        $this->newLine();
        $this->line($format === 'json' ? $report->toJson() : $report->toMarkdown());
        $this->newLine();
        $this->info(sprintf(
            'Report stored at %s | Acceptance: %.2f%% | Cutover ready: %s',
            $path,
            $report->acceptanceRate(),
            $report->isCutoverReady() ? 'YES' : 'no'
        ));

        return $report->isCutoverReady() ? Command::SUCCESS : Command::INVALID;
    }

    private function loadCases(): array
    {
        $path = (string) $this->option('fixtures');
        if ($path !== '') {
            if (! is_file($path)) {
                $this->error(sprintf('Fixture file not found: %s', $path));
                return [];
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                $this->error(sprintf('Fixture file is not valid JSON: %s', $path));
                return [];
            }

            return TestCaseRepository::fromArray($decoded);
        }

        return TestCaseRepository::canonical();
    }
}
