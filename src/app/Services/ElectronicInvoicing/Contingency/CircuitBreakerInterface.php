<?php

namespace App\Services\ElectronicInvoicing\Contingency;

/**
 * Three-state circuit breaker around the DIAN webservice.
 *
 * States (mirrors the spec section "Flujo de contingencia tecnologica"):
 *  - `closed`     -> all requests flow through.
 *  - `open`       -> requests must NOT be sent to DIAN; documents emit
 *                    under contingency until recovery_seconds elapse.
 *  - `half_open`  -> a single trial request is allowed to validate that
 *                    DIAN is responsive again.
 *
 * Implementations are responsible for trip / reset semantics and MUST
 * be safe to use concurrently (a Redis adapter, for instance, will lock
 * the increment). Tests use the in-memory adapter.
 */
interface CircuitBreakerInterface
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Current state, after applying any time-based recovery transition.
     */
    public function state(): string;

    /**
     * Returns true when callers are allowed to dispatch a request to
     * DIAN. When the breaker is open and the recovery window has not
     * elapsed yet, returns false.
     */
    public function allowsRequest(): bool;

    public function recordSuccess(): void;

    public function recordFailure(): void;

    /**
     * Snapshot for `/healthcheck` and observability.
     *
     * @return array{state: string, failures: int, last_failure_at: ?string, opened_at: ?string}
     */
    public function snapshot(): array;
}
