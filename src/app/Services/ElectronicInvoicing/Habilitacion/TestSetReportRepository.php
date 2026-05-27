<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

/**
 * Persistence-agnostic store for habilitacion test-set reports.
 *
 * The default implementation `FileTestSetReportRepository` writes a
 * single JSON file per run plus a `latest.json` pointer. Tests can swap
 * for the in-memory adapter to keep them hermetic.
 */
interface TestSetReportRepository
{
    /**
     * Persist the report and return the storage key (e.g. file path).
     */
    public function save(TestSetReport $report): string;

    /**
     * Return the most recently saved report, or null if none was stored.
     */
    public function latest(): ?TestSetReport;
}
