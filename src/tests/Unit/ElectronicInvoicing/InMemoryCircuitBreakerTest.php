<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Services\ElectronicInvoicing\Contingency\CircuitBreakerInterface;
use App\Services\ElectronicInvoicing\Contingency\InMemoryCircuitBreaker;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class InMemoryCircuitBreakerTest extends TestCase
{
    public function test_starts_closed_and_allows_requests(): void
    {
        $breaker = new InMemoryCircuitBreaker();
        $this->assertSame(CircuitBreakerInterface::STATE_CLOSED, $breaker->state());
        $this->assertTrue($breaker->allowsRequest());
    }

    public function test_trips_to_open_after_threshold_failures(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 3);
        for ($i = 0; $i < 3; $i++) {
            $breaker->recordFailure();
        }
        $this->assertSame(CircuitBreakerInterface::STATE_OPEN, $breaker->state());
        $this->assertFalse($breaker->allowsRequest());
    }

    public function test_success_resets_failure_count(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 3);
        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();
        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertSame(CircuitBreakerInterface::STATE_CLOSED, $breaker->state());
    }

    public function test_transitions_to_half_open_after_recovery_seconds_and_closes_on_success(): void
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $clock = function () use (&$now) {
            return $now;
        };
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 2, recoverySeconds: 60, clock: $clock);
        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertSame(CircuitBreakerInterface::STATE_OPEN, $breaker->state());

        $now = $now->modify('+59 seconds');
        $this->assertFalse($breaker->allowsRequest());

        $now = $now->modify('+2 seconds');
        $this->assertSame(CircuitBreakerInterface::STATE_HALF_OPEN, $breaker->state());
        $this->assertTrue($breaker->allowsRequest(), 'first trial must be allowed');
        $this->assertFalse($breaker->allowsRequest(), 'subsequent half-open requests are blocked');

        $breaker->recordSuccess();
        $this->assertSame(CircuitBreakerInterface::STATE_CLOSED, $breaker->state());
    }

    public function test_half_open_failure_reopens_the_breaker(): void
    {
        $now = new DateTimeImmutable('2026-01-01 10:00:00');
        $clock = function () use (&$now) {
            return $now;
        };
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 1, recoverySeconds: 30, clock: $clock);
        $breaker->recordFailure();
        $now = $now->modify('+31 seconds');
        $this->assertSame(CircuitBreakerInterface::STATE_HALF_OPEN, $breaker->state());
        $breaker->recordFailure();
        $this->assertSame(CircuitBreakerInterface::STATE_OPEN, $breaker->state());
    }

    public function test_snapshot_exposes_diagnostic_data(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 2);
        $breaker->recordFailure();
        $snap = $breaker->snapshot();
        $this->assertSame(CircuitBreakerInterface::STATE_CLOSED, $snap['state']);
        $this->assertSame(1, $snap['failures']);
        $this->assertNotNull($snap['last_failure_at']);
        $this->assertNull($snap['opened_at']);
    }
}
