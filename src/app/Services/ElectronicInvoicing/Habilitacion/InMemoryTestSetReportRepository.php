<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

class InMemoryTestSetReportRepository implements TestSetReportRepository
{
    /** @var TestSetReport[] */
    private array $reports = [];

    public function save(TestSetReport $report): string
    {
        $key = sprintf('memory://habilitacion/test-set-%03d.json', count($this->reports) + 1);
        $this->reports[] = $report;

        return $key;
    }

    public function latest(): ?TestSetReport
    {
        if ($this->reports === []) {
            return null;
        }

        return $this->reports[count($this->reports) - 1];
    }
}
