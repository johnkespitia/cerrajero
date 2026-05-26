<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use PHPUnit\Framework\TestCase;

class InMemoryMetricsRecorderTest extends TestCase
{
    public function test_increment_accumulates_total_across_labels(): void
    {
        $recorder = new InMemoryMetricsRecorder();

        $recorder->increment('electronic_documents_emitted_total', ['type' => 'fev', 'status' => 'ubl_built']);
        $recorder->increment('electronic_documents_emitted_total', ['type' => 'fev', 'status' => 'ubl_built']);
        $recorder->increment('electronic_documents_emitted_total', ['type' => 'dee_pos', 'status' => 'ubl_built']);

        $this->assertSame(3, $recorder->counter('electronic_documents_emitted_total'));
        $this->assertSame(
            2,
            $recorder->counterCountForLabels('electronic_documents_emitted_total', ['type' => 'fev'])
        );
        $this->assertSame(
            1,
            $recorder->counterCountForLabels('electronic_documents_emitted_total', ['type' => 'dee_pos'])
        );
    }

    public function test_observe_seconds_keeps_each_sample(): void
    {
        $recorder = new InMemoryMetricsRecorder();
        $recorder->observeSeconds('electronic_documents_signing_latency_seconds', 0.42);
        $recorder->observeSeconds('electronic_documents_signing_latency_seconds', 0.15);

        $this->assertSame(2, $recorder->histogramSamples('electronic_documents_signing_latency_seconds'));
    }

    public function test_set_gauge_returns_most_recent_value(): void
    {
        $recorder = new InMemoryMetricsRecorder();
        $recorder->setGauge('electronic_documents_in_contingency_count', 5.0);
        $recorder->setGauge('electronic_documents_in_contingency_count', 0.0);

        $this->assertSame(0.0, $recorder->gauge('electronic_documents_in_contingency_count'));
    }

    public function test_gauge_returns_null_when_never_set(): void
    {
        $recorder = new InMemoryMetricsRecorder();

        $this->assertNull($recorder->gauge('fiscal_certificate_days_to_expiry'));
    }

    public function test_reset_clears_all_state(): void
    {
        $recorder = new InMemoryMetricsRecorder();
        $recorder->increment('foo', [], 7);
        $recorder->setGauge('bar', 9);
        $recorder->observeSeconds('baz', 1.0);

        $recorder->reset();

        $this->assertSame(0, $recorder->counter('foo'));
        $this->assertNull($recorder->gauge('bar'));
        $this->assertSame(0, $recorder->histogramSamples('baz'));
    }
}
