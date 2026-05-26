<?php

namespace App\Services\ElectronicInvoicing\Contingency;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * In-memory circuit breaker used by default in tests and by the
 * synchronous in-process flow. Production wiring may rebind this to a
 * Redis / DB-backed implementation so the breaker survives restarts and
 * is shared across workers.
 *
 * Rules:
 *  - On `failure_threshold` consecutive failures the breaker trips to
 *    `open` and records `opened_at = now`.
 *  - When `now - opened_at >= recovery_seconds` the breaker transitions
 *    to `half_open`. A single successful call closes it; any failure
 *    re-opens it and resets the recovery timer.
 *  - `recordSuccess()` while `closed` resets the failure counter.
 */
final class InMemoryCircuitBreaker implements CircuitBreakerInterface
{
    private string $state = self::STATE_CLOSED;
    private int $failures = 0;
    private ?DateTimeImmutable $lastFailureAt = null;
    private ?DateTimeImmutable $openedAt = null;
    private bool $halfOpenInFlight = false;

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $recoverySeconds = 60,
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function state(): string
    {
        $this->maybeTransitionToHalfOpen();

        return $this->state;
    }

    public function allowsRequest(): bool
    {
        $this->maybeTransitionToHalfOpen();
        if ($this->state === self::STATE_CLOSED) {
            return true;
        }
        if ($this->state === self::STATE_HALF_OPEN) {
            if ($this->halfOpenInFlight) {
                return false;
            }
            $this->halfOpenInFlight = true;
            return true;
        }

        return false;
    }

    public function recordSuccess(): void
    {
        $this->maybeTransitionToHalfOpen();
        $this->failures = 0;
        $this->state = self::STATE_CLOSED;
        $this->lastFailureAt = null;
        $this->openedAt = null;
        $this->halfOpenInFlight = false;
    }

    public function recordFailure(): void
    {
        $this->maybeTransitionToHalfOpen();
        $this->lastFailureAt = $this->now();

        if ($this->state === self::STATE_HALF_OPEN) {
            $this->state = self::STATE_OPEN;
            $this->openedAt = $this->now();
            $this->halfOpenInFlight = false;
            return;
        }

        $this->failures++;
        if ($this->failures >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            $this->openedAt = $this->now();
        }
    }

    public function snapshot(): array
    {
        $this->maybeTransitionToHalfOpen();

        return [
            'state' => $this->state,
            'failures' => $this->failures,
            'last_failure_at' => $this->lastFailureAt?->format(DateTimeInterface::ATOM),
            'opened_at' => $this->openedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    private function maybeTransitionToHalfOpen(): void
    {
        if ($this->state !== self::STATE_OPEN || $this->openedAt === null) {
            return;
        }
        $elapsed = $this->now()->getTimestamp() - $this->openedAt->getTimestamp();
        if ($elapsed >= $this->recoverySeconds) {
            $this->state = self::STATE_HALF_OPEN;
            $this->halfOpenInFlight = false;
        }
    }

    private function now(): DateTimeImmutable
    {
        if ($this->clock !== null) {
            $value = ($this->clock)();
            if ($value instanceof DateTimeImmutable) {
                return $value;
            }
            if ($value instanceof DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($value);
            }
        }

        return DateTimeImmutable::createFromMutable(Carbon::now()->toDateTime());
    }
}
