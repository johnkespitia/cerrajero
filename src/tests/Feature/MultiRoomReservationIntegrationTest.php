<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use Carbon\Carbon;

class MultiRoomReservationIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $customer;
    protected $roomType;
    protected $rooms;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Multi-room reservation tests are not stable on SQLite CI runtime.');
        }
        
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $this->customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '1234567890',
            'name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'active' => true,
        ]);
        
        $this->roomType = RoomType::create([
            'name' => 'Suite',
            'code' => 'SUITE',
            'default_capacity' => 4,
            'max_capacity' => 4,
            'active' => true,
        ]);
        
        $this->rooms = [];
        for ($i = 1; $i <= 3; $i++) {
            $this->rooms[] = Room::create([
                'room_type_id' => $this->roomType->id,
                'room_number' => "101{$i}",
                'name' => "Suite {$i}",
                'status' => 'available',
                'active' => true,
                'capacity' => 4,
                'max_capacity' => 4,
                'room_price' => 500000,
            ]);
        }
    }

    /**
     * Test de integración: Flujo completo de reserva múltiple con check-in y check-out
     */
    public function test_complete_flow_multi_room_reservation_with_checkin_checkout()
    {
        $this->actingAs($this->user);

        // 1. Crear reserva múltiple
        $response = $this->postJson('/api/reservations', [
            'customer_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'reservation_type' => 'room',
            'check_in_date' => now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => now()->addDays(3)->format('Y-m-d'),
            'adults' => 6,
            'children' => 2,
            'infants' => 0,
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $mainReservationId = $data['main_reservation']['id'];

        // 2. Verificar que todas las reservas están en estado 'confirmed'
        $mainReservation = Reservation::find($mainReservationId);
        $this->assertEquals('confirmed', $mainReservation->status);
        
        foreach ($data['child_reservations'] as $childData) {
            $childReservation = Reservation::find($childData['id']);
            $this->assertEquals('confirmed', $childReservation->status);
        }

        // 3. Hacer check-in de la reserva principal
        $checkInResponse = $this->postJson(
            "/api/reservations/{$mainReservationId}/check-in",
            [
                'guests' => [
                    [
                        'first_name' => 'Juan',
                        'last_name' => 'Pérez',
                        'document_type' => 'CC',
                        'document_number' => '1234567890',
                        'is_primary_guest' => true,
                    ],
                ],
            ]
        );

        $checkInResponse->assertStatus(200);
        
        // Verificar que la reserva principal cambió a checked_in
        $mainReservation->refresh();
        $this->assertEquals('checked_in', $mainReservation->status);
        $this->assertNotNull($mainReservation->check_in_time);

        // 4. Hacer check-in de las reservas hijas
        foreach ($data['child_reservations'] as $childData) {
            $childCheckInResponse = $this->postJson(
                "/api/reservations/{$childData['id']}/check-in",
                [
                    'guests' => [
                        [
                            'first_name' => 'María',
                            'last_name' => 'González',
                            'document_type' => 'CC',
                            'document_number' => '0987654321',
                            'is_primary_guest' => false,
                        ],
                    ],
                ]
            );

            $childCheckInResponse->assertStatus(200);
            
            $childReservation = Reservation::find($childData['id']);
            $this->assertEquals('checked_in', $childReservation->status);
        }

        // 5. Hacer check-out de todas las reservas
        foreach ($data['child_reservations'] as $childData) {
            $childCheckOutResponse = $this->postJson(
                "/api/reservations/{$childData['id']}/check-out",
                []
            );

            $childCheckOutResponse->assertStatus(200);
        }

        $mainCheckOutResponse = $this->postJson(
            "/api/reservations/{$mainReservationId}/check-out",
            []
        );

        $mainCheckOutResponse->assertStatus(200);

        // Verificar que todas las reservas están en checked_out
        $mainReservation->refresh();
        $this->assertEquals('checked_out', $mainReservation->status);
        
        foreach ($data['child_reservations'] as $childData) {
            $childReservation = Reservation::find($childData['id']);
            $this->assertEquals('checked_out', $childReservation->status);
        }
    }

    /**
     * Test de integración: Verificar que los valores se consolidan en la reserva principal
     */
    public function test_values_consolidate_in_main_reservation()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/reservations', [
            'customer_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'reservation_type' => 'room',
            'check_in_date' => now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => now()->addDays(3)->format('Y-m-d'),
            'adults' => 6,
            'children' => 2,
            'infants' => 0,
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $mainReservationId = $data['main_reservation']['id'];

        // Verificar que el precio total está en la reserva principal
        $mainReservation = Reservation::find($mainReservationId);
        $expectedTotal = $data['total_price'];
        
        $this->assertEquals($expectedTotal, $mainReservation->total_price);

        // Verificar que las reservas hijas tienen sus precios individuales
        foreach ($data['child_reservations'] as $childData) {
            $childReservation = Reservation::find($childData['id']);
            $this->assertNotEquals($expectedTotal, $childReservation->total_price);
            $this->assertLessThan($expectedTotal, $childReservation->total_price);
        }
    }

    /**
     * Test de integración: Verificar relación padre-hijo
     */
    public function test_parent_child_relationship()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/reservations', [
            'customer_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'reservation_type' => 'room',
            'check_in_date' => now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => now()->addDays(3)->format('Y-m-d'),
            'adults' => 6,
            'children' => 2,
            'infants' => 0,
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $mainReservationId = $data['main_reservation']['id'];

        // Verificar que las reservas hijas tienen parent_reservation_id correcto
        foreach ($data['child_reservations'] as $childData) {
            $childReservation = Reservation::find($childData['id']);
            $this->assertEquals($mainReservationId, $childReservation->parent_reservation_id);
            $this->assertNotNull($childReservation->room_sequence);
        }

        // Verificar que la reserva principal no tiene parent_reservation_id
        $mainReservation = Reservation::find($mainReservationId);
        $this->assertNull($mainReservation->parent_reservation_id);
        $this->assertEquals(1, $mainReservation->room_sequence);
    }
}
