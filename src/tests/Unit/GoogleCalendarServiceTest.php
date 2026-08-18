<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\GoogleCalendarService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class GoogleCalendarServiceTest extends TestCase
{
    private function buildEventSummary(Reservation $reservation): string
    {
        $service = (new ReflectionClass(GoogleCalendarService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GoogleCalendarService::class, 'buildEventSummary');

        return $method->invoke($service, $reservation);
    }

    public function test_summary_for_day_pass_uses_short_name_and_pasadia_label(): void
    {
        $customer = new Customer([
            'customer_type' => 'person',
            'name' => 'Juan Carlos',
            'last_name' => 'Pérez Gómez',
        ]);
        $reservation = new Reservation([
            'reservation_type' => 'day_pass',
            'reservation_number' => 'RES2026081200003',
        ]);
        $reservation->setRelation('customer', $customer);

        $this->assertSame(
            'Juan Pérez (Pasadía) [RES2026081200003]',
            $this->buildEventSummary($reservation)
        );
    }

    public function test_summary_for_room_shows_room_type_instead_of_habitacion(): void
    {
        $customer = new Customer([
            'customer_type' => 'person',
            'name' => 'María Fernanda',
            'last_name' => 'García López',
        ]);
        $roomType = new RoomType(['name' => 'Suite Familiar']);
        $reservation = new Reservation([
            'reservation_type' => 'room',
            'reservation_number' => 'RES2026081200004',
        ]);
        $reservation->setRelation('customer', $customer);
        $reservation->setRelation('roomType', $roomType);

        $this->assertSame(
            'María García (Suite Familiar) [RES2026081200004]',
            $this->buildEventSummary($reservation)
        );
    }

    public function test_summary_for_room_without_room_type_falls_back_to_habitacion(): void
    {
        $customer = new Customer([
            'customer_type' => 'person',
            'name' => 'Pedro',
            'last_name' => 'Ramírez',
        ]);
        $reservation = new Reservation([
            'reservation_type' => 'room',
            'reservation_number' => 'RES2026081200005',
        ]);
        $reservation->setRelation('customer', $customer);

        $this->assertSame(
            'Pedro Ramírez (Habitación) [RES2026081200005]',
            $this->buildEventSummary($reservation)
        );
    }

    public function test_summary_for_company_customer_uses_company_name(): void
    {
        $customer = new Customer([
            'customer_type' => 'company',
            'company_name' => 'Constructora Andina S.A.S.',
        ]);
        $reservation = new Reservation([
            'reservation_type' => 'room',
            'reservation_number' => 'RES2026081200006',
        ]);
        $reservation->setRelation('customer', $customer);

        $this->assertSame(
            'Constructora Andina S.A.S. (Habitación) [RES2026081200006]',
            $this->buildEventSummary($reservation)
        );
    }
}
