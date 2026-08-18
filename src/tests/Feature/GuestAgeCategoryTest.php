<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAgeCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Guest age category tests require MySQL-compatible schema.');
        }

        $this->user = User::create([
            'name' => 'Age Category User',
            'email' => 'age-category@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '100200301',
            'name' => 'Ana',
            'last_name' => 'Cliente',
            'email' => 'ana.age@example.com',
            'phone_number' => '3001112233',
            'active' => true,
        ]);

        $roomType = RoomType::create([
            'name' => 'Sencilla',
            'code' => 'SGL-AGE',
            'default_capacity' => 2,
            'max_capacity' => 4,
            'active' => true,
        ]);

        $room = Room::create([
            'room_type_id' => $roomType->id,
            'room_number' => '201',
            'name' => 'Habitación 201',
            'status' => 'available',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 4,
            'room_price' => 200000,
        ]);

        $this->reservation = Reservation::create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'reservation_type' => 'room',
            'reservation_number' => 'RES-AGE-001',
            'check_in_date' => '2026-08-03',
            'check_out_date' => '2026-08-05',
            'adults' => 2,
            'children' => 1,
            'infants' => 1,
            'status' => 'confirmed',
            'total_price' => 400000,
        ]);
    }

    public function test_store_guest_with_birth_date_under_four_is_infant(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/reservations/{$this->reservation->id}/guests", [
            'first_name' => 'Bebé',
            'last_name' => 'Prueba',
            'birth_date' => '2024-01-01',
            'is_child' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('is_infant', true)
            ->assertJsonPath('is_child', false)
            ->assertJsonPath('age_category', 'infant');
    }

    public function test_store_guest_manual_infant_without_birth_date(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/reservations/{$this->reservation->id}/guests", [
            'first_name' => 'Infante',
            'last_name' => 'Manual',
            'is_infant' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('is_infant', true)
            ->assertJsonPath('is_child', false)
            ->assertJsonPath('age_category', 'infant');
    }

    public function test_store_guest_manual_child(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/reservations/{$this->reservation->id}/guests", [
            'first_name' => 'Niño',
            'last_name' => 'Manual',
            'is_child' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('is_infant', false)
            ->assertJsonPath('is_child', true)
            ->assertJsonPath('age_category', 'child');
    }

    public function test_store_guest_defaults_to_adult(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/reservations/{$this->reservation->id}/guests", [
            'first_name' => 'Adulto',
            'last_name' => 'Default',
            'birth_date' => '1990-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('is_infant', false)
            ->assertJsonPath('is_child', false)
            ->assertJsonPath('age_category', 'adult');
    }

    public function test_update_clears_infant_when_birth_date_is_four_or_more(): void
    {
        $this->actingAs($this->user);

        $create = $this->postJson("/api/reservations/{$this->reservation->id}/guests", [
            'first_name' => 'Casi',
            'last_name' => 'Bebé',
            'is_infant' => true,
        ]);
        $create->assertCreated();
        $guestId = $create->json('id');

        $response = $this->putJson("/api/reservations/{$this->reservation->id}/guests/{$guestId}", [
            'birth_date' => '2022-01-01',
            'is_infant' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('is_infant', false)
            ->assertJsonPath('age_category', 'adult');
    }
}
