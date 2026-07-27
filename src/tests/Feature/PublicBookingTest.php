<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DayPassCapacity;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected RoomType $roomType;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Public booking tests require MySQL-compatible schema.');
        }

        $this->roomType = RoomType::create([
            'name' => 'Estándar',
            'code' => 'STD',
            'default_capacity' => 2,
            'max_capacity' => 2,
            'base_price' => 200000,
            'active' => true,
        ]);

        $this->room = Room::create([
            'room_type_id' => $this->roomType->id,
            'room_number' => '101',
            'name' => 'Habitación 101',
            'status' => 'available',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 200000,
        ]);
    }

    public function test_public_config_is_accessible_without_auth(): void
    {
        $response = $this->getJson('/api/public/booking/config');

        $response->assertOk()
            ->assertJsonStructure([
                'enabled',
                'payment_modes' => ['request_only', 'deposit', 'full_payment'],
                'deposit_percentage',
                'payment_instructions',
            ]);
    }

    public function test_public_room_types_lists_active_types(): void
    {
        $response = $this->getJson('/api/public/booking/room-types');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Estándar']);
    }

    public function test_public_plans_includes_lodging_and_day_pass(): void
    {
        DayPassCapacity::updateOrCreate(
            ['date' => now()->format('Y-m-d')],
            [
                'max_capacity' => 150,
                'consumed_capacity' => 0,
                'adult_price' => 85000,
                'child_price' => 42000,
            ]
        );

        $response = $this->getJson('/api/public/booking/plans');

        $response->assertOk()
            ->assertJsonPath('day_pass.adult_price', 85000)
            ->assertJsonPath('day_pass.child_price', 42000)
            ->assertJsonStructure([
                'lodging' => ['room_types', 'packages', 'additional_services'],
                'day_pass' => ['adult_price', 'child_price', 'description', 'services'],
                'config' => ['check_in_time', 'check_out_time'],
            ]);
    }

    public function test_public_availability_for_room(): void
    {
        $checkIn = now()->addDays(3)->format('Y-m-d');
        $checkOut = now()->addDays(5)->format('Y-m-d');

        $response = $this->getJson('/api/public/booking/availability?' . http_build_query([
            'reservation_type' => 'room',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'room_type_id' => $this->roomType->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('reservation_type', 'room');
    }

    public function test_public_availability_for_day_pass(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');
        DayPassCapacity::create([
            'date' => $date,
            'max_capacity' => 50,
            'consumed_capacity' => 0,
            'adult_price' => 80000,
            'child_price' => 40000,
        ]);

        $response = $this->getJson('/api/public/booking/availability?' . http_build_query([
            'reservation_type' => 'day_pass',
            'check_in_date' => $date,
            'adults' => 2,
            'children' => 0,
        ]));

        $response->assertOk()
            ->assertJsonPath('reservation_type', 'day_pass')
            ->assertJsonPath('available', true);
    }

    public function test_public_calendar_returns_monthly_days(): void
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        $response = $this->getJson('/api/public/booking/calendar?' . http_build_query([
            'year' => $year,
            'month' => $month,
            'reservation_type' => 'room',
            'adults' => 2,
            'children' => 0,
            'room_type_id' => $this->roomType->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('year', $year)
            ->assertJsonPath('month', $month)
            ->assertJsonStructure(['days' => [['date', 'status', 'available_count', 'total_count']]]);
    }

    public function test_public_booking_creates_pending_reservation(): void
    {
        $checkIn = now()->addDays(4)->format('Y-m-d');
        $checkOut = now()->addDays(6)->format('Y-m-d');

        $response = $this->postJson('/api/public/booking/reservations', [
            'reservation_type' => 'room',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'room_type_id' => $this->roomType->id,
            'payment_mode' => 'request_only',
            'customer' => [
                'customer_type' => 'person',
                'dni' => '1234567890',
                'name' => 'Juan',
                'last_name' => 'Pérez',
                'email' => 'juan@example.com',
                'phone_number' => '3001234567',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('reservation.web_payment_mode', 'request_only');

        $this->assertDatabaseHas('reservations', [
            'status' => 'pending',
            'web_payment_mode' => 'request_only',
            'contact_channel' => 'website',
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'juan@example.com',
        ]);
    }

    public function test_public_booking_accepts_payment_receipt_for_deposit(): void
    {
        $checkIn = now()->addDays(4)->format('Y-m-d');
        $checkOut = now()->addDays(6)->format('Y-m-d');

        $createResponse = $this->postJson('/api/public/booking/reservations', [
            'reservation_type' => 'room',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'room_type_id' => $this->roomType->id,
            'payment_mode' => 'deposit',
            'customer' => [
                'customer_type' => 'person',
                'dni' => '9876543210',
                'name' => 'Ana',
                'last_name' => 'Gómez',
                'email' => 'ana@example.com',
                'phone_number' => '3009876543',
            ],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('requires_payment_receipt', true);

        $reservationId = $createResponse->json('reservation.id');

        $file = \Illuminate\Http\UploadedFile::fake()->create('recibo.pdf', 120, 'application/pdf');

        $uploadResponse = $this->post("/api/public/booking/reservations/{$reservationId}/payment-receipt", [
            'customer_email' => 'ana@example.com',
            'receipt' => $file,
        ]);

        $uploadResponse->assertCreated()
            ->assertJsonStructure(['receipt_url', 'reservation']);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'web_payment_mode' => 'deposit',
        ]);

        $reservation = Reservation::findOrFail($reservationId);
        $this->assertNotNull($reservation->web_payment_receipt_url);
        $this->assertNotNull($reservation->web_payment_receipt_uploaded_at);
    }

    public function test_payment_receipt_rejects_wrong_customer_email(): void
    {
        $checkIn = now()->addDays(4)->format('Y-m-d');
        $checkOut = now()->addDays(6)->format('Y-m-d');

        $createResponse = $this->postJson('/api/public/booking/reservations', [
            'reservation_type' => 'room',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'room_type_id' => $this->roomType->id,
            'payment_mode' => 'full_payment',
            'customer' => [
                'customer_type' => 'person',
                'dni' => '5555555555',
                'name' => 'Luis',
                'last_name' => 'Ruiz',
                'email' => 'luis@example.com',
            ],
        ]);

        $reservationId = $createResponse->json('reservation.id');
        $file = \Illuminate\Http\UploadedFile::fake()->image('pago.jpg');

        $this->post("/api/public/booking/reservations/{$reservationId}/payment-receipt", [
            'customer_email' => 'otro@example.com',
            'receipt' => $file,
        ])->assertForbidden();
    }
}
