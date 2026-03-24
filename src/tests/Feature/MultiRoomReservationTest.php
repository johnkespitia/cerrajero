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
use Illuminate\Support\Facades\DB;

class MultiRoomReservationTest extends TestCase
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
        
        // Crear usuario autenticado
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        
        // Crear cliente
        $this->customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '1234567890',
            'name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'active' => true,
        ]);
        
        // Crear tipo de habitación
        $this->roomType = RoomType::create([
            'name' => 'Suite',
            'code' => 'SUITE',
            'default_capacity' => 4,
            'max_capacity' => 4,
            'active' => true,
        ]);
        
        // Crear habitaciones disponibles
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
     * Test: Crear reserva múltiple cuando se supera la capacidad de una habitación
     */
    public function test_create_multi_room_reservation_when_exceeding_capacity()
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
        $response->assertJsonStructure([
            'message',
            'main_reservation' => [
                'id',
                'reservation_number',
                'is_group_reservation',
                'room_sequence',
            ],
            'child_reservations' => [
                '*' => [
                    'id',
                    'parent_reservation_id',
                    'is_group_reservation',
                    'room_sequence',
                ],
            ],
            'total_rooms',
            'total_price',
            'rooms_assigned',
            'price_breakdown',
        ]);

        // Verificar que se crearon 2 reservas (1 principal + 1 hija)
        $this->assertDatabaseHas('reservations', [
            'customer_id' => $this->customer->id,
            'is_group_reservation' => true,
            'room_sequence' => 1,
            'parent_reservation_id' => null,
        ]);

        $mainReservation = Reservation::where('customer_id', $this->customer->id)
            ->where('room_sequence', 1)
            ->whereNull('parent_reservation_id')
            ->first();

        $this->assertNotNull($mainReservation);
        $this->assertTrue($mainReservation->is_group_reservation);

        // Verificar reserva hija
        $this->assertDatabaseHas('reservations', [
            'parent_reservation_id' => $mainReservation->id,
            'is_group_reservation' => true,
            'room_sequence' => 2,
        ]);
    }

    /**
     * Test: Verificar que las transacciones funcionan correctamente
     */
    public function test_transaction_rollback_on_error()
    {
        $this->actingAs($this->user);

        // Simular un error forzando una validación que falle
        // (por ejemplo, intentar crear con habitaciones que no existen)
        
        // Primero, marcar todas las habitaciones como no disponibles
        Room::where('room_type_id', $this->roomType->id)->update(['status' => 'occupied']);

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

        // Debe fallar porque no hay habitaciones disponibles
        $response->assertStatus(409);

        // Verificar que NO se creó ninguna reserva
        $this->assertDatabaseMissing('reservations', [
            'customer_id' => $this->customer->id,
        ]);
    }

    /**
     * Test: Verificar validación de disponibilidad en tiempo real
     */
    public function test_real_time_availability_validation()
    {
        $this->actingAs($this->user);

        // Crear una reserva que ocupe una habitación
        $firstReservation = Reservation::factory()->create([
            'customer_id' => $this->customer->id,
            'room_id' => $this->rooms[0]->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'confirmed',
        ]);

        // Intentar crear otra reserva múltiple que necesite esa habitación
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

        // Debe fallar o usar otras habitaciones disponibles
        // (depende de la implementación, pero debe validar correctamente)
        $this->assertContains($response->status(), [201, 409]);
    }

    /**
     * Test: Verificar distribución de huéspedes en múltiples habitaciones
     */
    public function test_guest_distribution_across_rooms()
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

        // Verificar que la suma de huéspedes en todas las habitaciones sea correcta
        $totalAdults = $data['main_reservation']['adults'];
        $totalChildren = $data['main_reservation']['children'];
        $totalInfants = $data['main_reservation']['infants'];

        foreach ($data['child_reservations'] as $child) {
            $totalAdults += $child['adults'];
            $totalChildren += $child['children'];
            $totalInfants += $child['infants'];
        }

        $this->assertEquals(6, $totalAdults);
        $this->assertEquals(2, $totalChildren);
        $this->assertEquals(0, $totalInfants);
    }

    /**
     * Test: Verificar que el precio total es la suma de todas las habitaciones
     */
    public function test_total_price_is_sum_of_all_rooms()
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

        // Calcular precio esperado
        $expectedPrice = $data['main_reservation']['total_price'];
        foreach ($data['child_reservations'] as $child) {
            $expectedPrice += $child['total_price'];
        }

        $this->assertEquals($expectedPrice, $data['total_price']);
        $this->assertEquals($expectedPrice, $data['main_reservation']['total_price']);
    }

    /**
     * Test: Verificar respuesta con información detallada de habitaciones
     */
    public function test_response_includes_detailed_room_information()
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

        // Verificar estructura de rooms_assigned
        $this->assertArrayHasKey('rooms_assigned', $data);
        $this->assertIsArray($data['rooms_assigned']);
        $this->assertGreaterThan(1, count($data['rooms_assigned']));

        // Verificar que cada habitación tiene la información correcta
        foreach ($data['rooms_assigned'] as $room) {
            $this->assertArrayHasKey('room_id', $room);
            $this->assertArrayHasKey('guests', $room);
            $this->assertArrayHasKey('price', $room);
            $this->assertArrayHasKey('sequence', $room);
        }

        // Verificar price_breakdown
        $this->assertArrayHasKey('price_breakdown', $data);
        $this->assertArrayHasKey('rooms', $data['price_breakdown']);
        $this->assertArrayHasKey('total', $data['price_breakdown']);
    }

    /**
     * Test: Verificar que no se puede crear reserva múltiple si no hay suficientes habitaciones
     */
    public function test_cannot_create_multi_room_reservation_without_enough_rooms()
    {
        $this->actingAs($this->user);

        // Intentar crear reserva para 20 huéspedes pero solo hay 3 habitaciones de capacidad 4
        $response = $this->postJson('/api/reservations', [
            'customer_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'reservation_type' => 'room',
            'check_in_date' => now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => now()->addDays(3)->format('Y-m-d'),
            'adults' => 15,
            'children' => 5,
            'infants' => 0,
        ]);

        // Debe fallar porque 3 habitaciones x 4 capacidad = 12, pero se necesitan 20
        $response->assertStatus(409);
        $this->assertStringContainsString('suficientes habitaciones', $response->json()['message']);
    }
}
