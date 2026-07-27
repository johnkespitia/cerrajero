<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Services\CourtesyGuestDiscountCalculator;
use Tests\TestCase;

class CourtesyGuestDiscountCalculatorTest extends TestCase
{
    public function test_calculates_courtesy_using_discounted_lodging_and_services_per_guest(): void
    {
        $reservation = new Reservation([
            'adults' => 40,
            'children' => 0,
            'courtesy_guests' => 2,
            'calculated_price' => 720000,
        ]);

        $reservation->setRelation('additionalServices', collect([
            (object) ['quantity' => 40, 'total' => 400000.0],
        ]));

        $result = app(CourtesyGuestDiscountCalculator::class)->calculate($reservation);

        $this->assertSame(2, $result['courtesy_guests']);
        $this->assertSame(18000.0, $result['lodging_per_guest']);
        $this->assertSame(10000.0, $result['services_per_guest']);
        $this->assertSame(28000.0, $result['per_guest_total']);
        $this->assertSame(56000.0, $result['total']);
    }

    public function test_caps_courtesy_guests_to_chargeable_guests(): void
    {
        $reservation = new Reservation([
            'adults' => 5,
            'children' => 0,
            'courtesy_guests' => 10,
            'calculated_price' => 50000,
        ]);
        $reservation->setRelation('additionalServices', collect());

        $result = app(CourtesyGuestDiscountCalculator::class)->calculate($reservation);

        $this->assertSame(5, $result['courtesy_guests']);
        $this->assertSame(50000.0, $result['total']);
    }
}
