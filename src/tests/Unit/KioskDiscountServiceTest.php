<?php

namespace Tests\Unit;

use App\Models\KioskCoupon;
use App\Services\KioskDiscountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KioskDiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    private KioskDiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new KioskDiscountService();
        Carbon::setTestNow(Carbon::parse('2026-08-03'));
    }

    private function makeCoupon(array $overrides = []): KioskCoupon
    {
        return KioskCoupon::create(array_merge([
            'code' => 'VERANO10',
            'name' => 'Verano 10%',
            'type' => 'percentage',
            'value' => 10,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-08-31',
            'max_uses' => null,
            'used_count' => 0,
            'active' => true,
        ], $overrides));
    }

    public function test_percentage_coupon_discount(): void
    {
        $this->makeCoupon();
        $resolved = $this->service->resolve(100000, 'verano10', 0);

        $this->assertSame('VERANO10', $resolved['coupon_code']);
        $this->assertSame(10000.0, $resolved['coupon_discount']);
        $this->assertSame(0.0, $resolved['manual_discount']);
        $this->assertSame(90000.0, $resolved['payable']);
    }

    public function test_fixed_coupon_caps_at_subtotal(): void
    {
        $this->makeCoupon([
            'code' => 'FIJO50',
            'type' => 'fixed',
            'value' => 50000,
        ]);

        $resolved = $this->service->resolve(30000, 'FIJO50', 0);
        $this->assertSame(30000.0, $resolved['coupon_discount']);
        $this->assertSame(0.0, $resolved['payable']);
    }

    public function test_coupon_and_manual_stack(): void
    {
        $this->makeCoupon();
        $resolved = $this->service->resolve(100000, 'VERANO10', 5000);

        $this->assertSame(10000.0, $resolved['coupon_discount']);
        $this->assertSame(5000.0, $resolved['manual_discount']);
        $this->assertSame(15000.0, $resolved['discount_total']);
        $this->assertSame(85000.0, $resolved['payable']);
    }

    public function test_manual_cannot_exceed_remainder_after_coupon(): void
    {
        $this->makeCoupon(['type' => 'fixed', 'value' => 80000, 'code' => 'BIG']);
        $resolved = $this->service->resolve(100000, 'BIG', 50000);

        $this->assertSame(80000.0, $resolved['coupon_discount']);
        $this->assertSame(20000.0, $resolved['manual_discount']);
        $this->assertSame(0.0, $resolved['payable']);
    }

    public function test_null_max_uses_is_unlimited(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => null, 'used_count' => 999]);
        $this->assertTrue($coupon->isValid());
    }

    public function test_exhausted_coupon_rejected(): void
    {
        $this->makeCoupon(['max_uses' => 1, 'used_count' => 1]);

        $this->expectException(ValidationException::class);
        $this->service->resolve(10000, 'VERANO10', 0);
    }

    public function test_invalid_coupon_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->resolve(10000, 'NOEXISTE', 0);
    }
}
