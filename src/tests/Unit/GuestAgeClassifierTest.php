<?php

namespace Tests\Unit;

use App\Services\GuestAgeClassifier;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GuestAgeClassifierTest extends TestCase
{
    private GuestAgeClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new GuestAgeClassifier();
    }

    public function test_birth_date_under_four_at_check_in_is_infant(): void
    {
        $result = $this->classifier->resolve(
            '2023-06-01',
            false,
            true,
            '2026-08-03'
        );

        $this->assertTrue($result['is_infant']);
        $this->assertFalse($result['is_child']);
        $this->assertSame('infant', $result['age_category']);
        $this->assertSame(3, $result['age']);
    }

    public function test_birth_date_four_or_more_is_not_infant_even_if_flagged(): void
    {
        $result = $this->classifier->resolve(
            '2022-01-01',
            true,
            false,
            '2026-08-03'
        );

        $this->assertFalse($result['is_infant']);
        $this->assertFalse($result['is_child']);
        $this->assertSame('adult', $result['age_category']);
        $this->assertSame(4, $result['age']);
    }

    public function test_manual_infant_without_birth_date(): void
    {
        $result = $this->classifier->resolve(null, true, true, '2026-08-03');

        $this->assertTrue($result['is_infant']);
        $this->assertFalse($result['is_child']);
        $this->assertSame('infant', $result['age_category']);
        $this->assertNull($result['age']);
    }

    public function test_manual_child_when_not_infant(): void
    {
        $result = $this->classifier->resolve(null, false, true, '2026-08-03');

        $this->assertFalse($result['is_infant']);
        $this->assertTrue($result['is_child']);
        $this->assertSame('child', $result['age_category']);
    }

    public function test_default_is_adult(): void
    {
        $result = $this->classifier->resolve('1990-01-01', false, false, Carbon::parse('2026-08-03'));

        $this->assertFalse($result['is_infant']);
        $this->assertFalse($result['is_child']);
        $this->assertSame('adult', $result['age_category']);
    }

    public function test_child_flag_cleared_when_auto_infant(): void
    {
        $result = $this->classifier->resolve('2025-01-01', false, true, '2026-08-03');

        $this->assertTrue($result['is_infant']);
        $this->assertFalse($result['is_child']);
        $this->assertSame('infant', $result['age_category']);
    }
}
