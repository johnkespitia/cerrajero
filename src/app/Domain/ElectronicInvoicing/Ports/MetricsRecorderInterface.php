<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * Port for emitting Electronic Invoicing metrics.
 *
 * Implementations decide where the metrics flow (Prometheus, StatsD, no-op
 * in tests, in-memory ring buffer in CI). The domain code MUST depend on
 * this interface only, never on a concrete client.
 *
 * Metric contract (kept stable across slices):
 * - Counter names use `_total` suffix.
 * - Histogram names use `_seconds` suffix and report seconds, not ms.
 * - Gauges report the current value of an observable.
 * - Labels are flat key=>value (string => string); avoid high-cardinality
 *   labels (no `electronic_document_id`, no `correlation_id`).
 *
 * Required metrics for the EI pipeline (the dispatcher/reconciler slices
 * will instrument these once they land):
 * - electronic_documents_emitted_total{type,status}
 * - electronic_documents_signing_latency_seconds  (histogram)
 * - electronic_documents_dian_latency_seconds    (histogram, by operation)
 * - electronic_documents_in_contingency_count    (gauge)
 * - electronic_documents_dead_letter_total       (counter)
 * - electronic_documents_circuit_breaker_state   (gauge: 0=closed,1=half_open,2=open)
 * - dian_soap_calls_total{operation,result}
 * - fiscal_certificate_days_to_expiry            (gauge)
 * - numbering_allocator_conflicts_total
 * - dian_unknown_error_codes_total
 */
interface MetricsRecorderInterface
{
    /**
     * Increment a counter by `$value` (default 1) with optional labels.
     *
     * @param  array<string,string>  $labels
     */
    public function increment(string $name, array $labels = [], int|float $value = 1): void;

    /**
     * Record a value in seconds in a histogram bucket.
     *
     * @param  array<string,string>  $labels
     */
    public function observeSeconds(string $name, float $seconds, array $labels = []): void;

    /**
     * Set a gauge to the supplied value.
     *
     * @param  array<string,string>  $labels
     */
    public function setGauge(string $name, float $value, array $labels = []): void;
}
