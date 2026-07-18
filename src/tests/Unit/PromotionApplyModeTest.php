<?php

namespace Tests\Unit;

use App\Models\Promotion;
use Carbon\Carbon;
use Tests\TestCase;

class PromotionApplyModeTest extends TestCase
{
    public function test_fixed_total_discount_caps_at_base_price(): void
    {
        $promotion = new Promotion([
            'type' => 'fixed',
            'value' => 50000,
            'apply_mode' => 'total',
            'active' => true,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        $this->assertSame(50000.0, $promotion->calculateDiscount(200000, 1, Carbon::today()->format('Y-m-d'), 4));
        $this->assertSame(30000.0, $promotion->calculateDiscount(30000, 1, Carbon::today()->format('Y-m-d'), 4));
    }

    public function test_fixed_per_guest_multiplies_by_chargeable_guests(): void
    {
        $promotion = new Promotion([
            'type' => 'fixed',
            'value' => 25000,
            'apply_mode' => 'per_guest',
            'active' => true,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        $this->assertSame(100000.0, $promotion->calculateDiscount(500000, 1, Carbon::today()->format('Y-m-d'), 4));
    }

    public function test_percentage_per_guest_uses_adult_and_child_prices_for_day_pass(): void
    {
        $promotion = new Promotion([
            'type' => 'percentage',
            'value' => 10,
            'apply_mode' => 'per_guest',
            'active' => true,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        $discount = $promotion->calculateDiscount(
            250000,
            1,
            Carbon::today()->format('Y-m-d'),
            3,
            [
                'adults' => 2,
                'children' => 1,
                'adult_price' => 100000,
                'child_price' => 50000,
            ]
        );

    public function test_percentage_per_guest_uses_lodging_prices_for_room_stay(): void
    {
        $promotion = new Promotion([
            'type' => 'percentage',
            'value' => 10,
            'apply_mode' => 'per_guest',
            'active' => true,
            'valid_from' => Carbon::today()->subDay(),
            'valid_until' => Carbon::today()->addDay(),
        ]);

        $discount = $promotion->calculateDiscount(
            320000,
            2,
            Carbon::today()->format('Y-m-d'),
            2,
            [
                'adults' => 2,
                'children' => 0,
                'adult_price' => 100000,
                'child_price' => 100000,
            ]
        );

        $this->assertSame(20000.0, $discount);
    }
}
