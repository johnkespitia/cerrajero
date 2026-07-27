<?php

namespace App\Console\Commands\ElectronicInvoicing;

use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Illuminate\Console\Command;

/**
 * Prints the latest habilitacion test-set report.
 *
 * Usage:
 *  - `php artisan electronic-invoicing:test-set-report`
 *  - `php artisan electronic-invoicing:test-set-report --format=json`
 */
class PrintTestSetReport extends Command
{
    protected $signature = 'electronic-invoicing:test-set-report {--format=markdown}';

    protected $description = 'Print the most recent DIAN habilitacion test-set report.';

    public function handle(TestSetReportRepository $reports): int
    {
        $report = $reports->latest();
        if ($report === null) {
            $this->warn('No habilitacion test-set report has been generated yet.');
            return Command::INVALID;
        }
        $format = (string) $this->option('format');
        $this->line($format === 'json' ? $report->toJson() : $report->toMarkdown());
        $this->newLine();
        $this->info(sprintf(
            'Acceptance: %.2f%% | Cutover ready: %s',
            $report->acceptanceRate(),
            $report->isCutoverReady() ? 'YES' : 'no'
        ));

        return Command::SUCCESS;
    }
}
