<?php

namespace Tests\Feature;

use App\Models\HotelClosure;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_closure_success(): void
    {
        $response = $this->postJson('/api/hotel-closures', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'reason' => 'Mantenimiento',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('hotel_closures', ['start_date' => '2026-10-01']);
    }

    public function test_create_closure_fails_if_overlaps_existing(): void
    {
        HotelClosure::create(['start_date' => '2026-11-01', 'end_date' => '2026-11-10', 'status' => 'active']);

        $response = $this->postJson('/api/hotel-closures', [
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-12',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Ya existe un cierre']);
    }

    public function test_create_closure_fails_if_active_reservations_exist(): void
    {
        // Crear reserva activa que solapa
        Reservation::factory()->create([
            'check_in_date' => '2026-12-10',
            'check_out_date' => '2026-12-15',
            'status' => 'confirmed',
        ]);

        $response = $this->postJson('/api/hotel-closures', [
            'start_date' => '2026-12-12',
            'end_date' => '2026-12-14',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['Existen']);
    }

    public function test_store_reservation_fails_if_closure_exists(): void
    {
        HotelClosure::create(['start_date' => '2026-09-20', 'end_date' => '2026-09-25', 'status' => 'active']);

        $payload = [
            'customer_id' => 1,
            'room_type_id' => 1,
            'reservation_type' => 'room',
            'check_in_date' => '2026-09-22',
            'check_out_date' => '2026-09-24',
            'adults' => 2,
            'deposit_amount' => 0,
        ];

        $response = $this->postJson('/api/reservations', $payload);
        $response->assertStatus(422);
    }
}
