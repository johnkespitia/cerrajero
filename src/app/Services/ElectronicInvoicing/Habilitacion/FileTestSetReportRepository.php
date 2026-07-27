<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

use RuntimeException;

/**
 * Stores reports on the local filesystem at
 * `storage/electronic-invoicing/test-sets/`.
 *
 * Each run gets `<timestamp>.json` plus the `latest.json` pointer.
 */
class FileTestSetReportRepository implements TestSetReportRepository
{
    public function __construct(private readonly string $baseDir)
    {
    }

    public function save(TestSetReport $report): string
    {
        if (! is_dir($this->baseDir) && ! mkdir($this->baseDir, 0755, true) && ! is_dir($this->baseDir)) {
            throw new RuntimeException(sprintf('Cannot create test-set report directory: %s', $this->baseDir));
        }
        $filename = sprintf('%s/%s.json', $this->baseDir, str_replace(':', '-', date('Y-m-d_H-i-s')));
        file_put_contents($filename, $report->toJson());
        file_put_contents($this->baseDir . '/latest.json', $report->toJson());

        return $filename;
    }

    public function latest(): ?TestSetReport
    {
        $path = $this->baseDir . '/latest.json';
        if (! is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        return new TestSetReport($decoded);
    }
}
