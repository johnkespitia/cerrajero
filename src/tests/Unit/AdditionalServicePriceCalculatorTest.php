<?php

namespace Tests\Unit;

use App\Models\AdditionalService;
use App\Models\Reservation;
use App\Services\AdditionalServicePriceCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class AdditionalServicePriceCalculatorTest extends TestCase
{
    private AdditionalServicePriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new AdditionalServicePriceCalculator();
    }

    public function test_calculate_total_scales_with_item_quantity_one_time(): void
    {
        $service = new AdditionalService([
            'price' => 25000,
            'billing_type' => 'one_time',
        ]);
        $reservation = new Reservation([
            'reservation_type' => 'day_pass',
        ]);

        $calc = $this->calculator->calculateTotal($service, $reservation, 4);

        $this->assertSame(4, $calc['quantity']);
        $this->assertEquals(1.0, $calc['service_days']);
        $this->assertEquals(100000.0, $calc['total']);
    }

    public function test_calculate_total_scales_with_item_quantity_per_day(): void
    {
        $service = new AdditionalService([
            'price' => 10000,
            'billing_type' => 'per_day',
        ]);
        $reservation = new Reservation([
            'reservation_type' => 'room',
            'check_in_date' => Carbon::parse('2026-08-01'),
            'check_out_date' => Carbon::parse('2026-08-04'),
        ]);

        $calc = $this->calculator->calculateTotal($service, $reservation, 2);

        $this->assertSame(2, $calc['quantity']);
        $this->assertEquals(3.0, $calc['service_days']);
        $this->assertEquals(60000.0, $calc['total']);
    }

    public function test_calculate_total_enforces_minimum_quantity_of_one(): void
    {
        $service = new AdditionalService([
            'price' => 15000,
            'billing_type' => 'one_time',
        ]);
        $reservation = new Reservation([
            'reservation_type' => 'day_pass',
        ]);

        $calc = $this->calculator->calculateTotal($service, $reservation, 0);

        $this->assertSame(1, $calc['quantity']);
        $this->assertEquals(15000.0, $calc['total']);
    }
}
