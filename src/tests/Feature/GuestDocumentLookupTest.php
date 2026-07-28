<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestDocumentLookupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Guest lookup tests require MySQL-compatible schema.');
        }

        $this->user = User::create([
            'name' => 'Lookup User',
            'email' => 'lookup@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '100200300',
            'name' => 'Ana',
            'last_name' => 'Cliente',
            'email' => 'ana.cliente@example.com',
            'phone_number' => '3001112233',
            'active' => true,
        ]);

        $roomType = RoomType::create([
            'name' => 'Sencilla',
            'code' => 'SGL',
            'default_capacity' => 2,
            'max_capacity' => 2,
            'active' => true,
        ]);

        $room = Room::create([
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'name' => 'Habitación 101',
            'status' => 'available',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 200000,
        ]);

        $this->reservation = Reservation::create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'reservation_type' => 'room',
            'reservation_number' => 'RES-LOOKUP-001',
            'check_in_date' => now()->subDays(10)->format('Y-m-d'),
            'check_out_date' => now()->subDays(8)->format('Y-m-d'),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'status' => 'checked_out',
            'total_price' => 400000,
        ]);
    }

    public function test_lookup_returns_not_found_for_unknown_document(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/guests/lookup?document_number=999999999');

        $response->assertOk()->assertJson(['found' => false]);
    }

    public function test_lookup_returns_customer_when_only_customer_exists(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/guests/lookup?document_number=100200300&document_type=CC');

        $response->assertOk()->assertJson([
            'found' => true,
            'source' => 'customer',
            'guest' => [
                'first_name' => 'Ana',
                'last_name' => 'Cliente',
                'document_number' => '100200300',
                'email' => 'ana.cliente@example.com',
                'phone' => '3001112233',
            ],
        ]);
    }

    public function test_lookup_prefers_previous_guest_over_customer(): void
    {
        $this->actingAs($this->user);

        ReservationGuest::create([
            'reservation_id' => $this->reservation->id,
            'first_name' => 'Ana',
            'last_name' => 'Huésped',
            'document_type' => 'CC',
            'document_number' => '100200300',
            'email' => 'ana.huesped@example.com',
            'phone' => '3009998877',
            'health_insurance_name' => 'Sura',
            'health_insurance_type' => 'national',
            'nationality' => 'Colombiana',
            'gender' => 'female',
            'birth_date' => '1990-05-15',
            'is_primary_guest' => true,
        ]);

        $response = $this->getJson('/api/guests/lookup?document_number=100200300&document_type=CC');

        $response->assertOk()->assertJson([
            'found' => true,
            'source' => 'both',
            'guest' => [
                'first_name' => 'Ana',
                'last_name' => 'Huésped',
                'email' => 'ana.huesped@example.com',
                'phone' => '3009998877',
                'health_insurance_name' => 'Sura',
                'nationality' => 'Colombiana',
                'gender' => 'female',
                'birth_date' => '1990-05-15',
            ],
        ]);
    }

    public function test_lookup_requires_document_number(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/guests/lookup');

        $response->assertStatus(422);
    }
}
