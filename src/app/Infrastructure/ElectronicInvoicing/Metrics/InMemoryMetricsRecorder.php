<?php

namespace App\Infrastructure\ElectronicInvoicing\Metrics;

use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;

/**
 * In-memory metrics recorder. Default binding for the EI pipeline until
 * the production observability stack (Prometheus exporter) is wired up.
 *
 * Useful for tests: each test can `$recorder->counter('foo')` to assert
 * the pipeline actually observed the expected metric without spinning up
 * an exporter.
 *
 * NOT a no-op: keeps every sample in process memory. In production the
 * container will be rebound to a Prometheus-backed recorder.
 */
class InMemoryMetricsRecorder implements MetricsRecorderInterface
{
    /** @var array<string, array<int, array{labels: array<string,string>, value: int|float}>> */
    private array $counters = [];
    /** @var array<string, array<int, array{labels: array<string,string>, value: float}>> */
    private array $histograms = [];
    /** @var array<string, array<int, array{labels: array<string,string>, value: float}>> */
    private array $gauges = [];

    public function increment(string $name, array $labels = [], int|float $value = 1): void
    {
        $this->counters[$name][] = ['labels' => $labels, 'value' => $value];
    }

    public function observeSeconds(string $name, float $seconds, array $labels = []): void
    {
        $this->histograms[$name][] = ['labels' => $labels, 'value' => $seconds];
    }

    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $this->gauges[$name][] = ['labels' => $labels, 'value' => $value];
    }

    /**
     * Sum of every increment recorded against $name, regardless of labels.
     */
    public function counter(string $name): int|float
    {
        return array_sum(array_column($this->counters[$name] ?? [], 'value'));
    }

    /**
     * Number of samples recorded against histogram $name.
     */
    public function histogramSamples(string $name): int
    {
        return count($this->histograms[$name] ?? []);
    }

    /**
     * Most recent value set on gauge $name (returns null if never set).
     */
    public function gauge(string $name): float|null
    {
        $samples = $this->gauges[$name] ?? [];
        if ($samples === []) {
            return null;
        }
        $last = end($samples);

        return (float) $last['value'];
    }

    /**
     * Number of samples for a counter matching ALL given labels (label
     * subset match).
     *
     * @param  array<string,string>  $labels
     */
    public function counterCountForLabels(string $name, array $labels): int
    {
        $matches = 0;
        foreach ($this->counters[$name] ?? [] as $sample) {
            $ok = true;
            foreach ($labels as $key => $value) {
                if (($sample['labels'][$key] ?? null) !== $value) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $matches += 1;
            }
        }

        return $matches;
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->histograms = [];
        $this->gauges = [];
    }
}
