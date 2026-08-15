<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomOccupancyReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected RoomType $roomType;
    protected Room $room;
    protected Room $maintenanceRoom;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Room occupancy report tests require MySQL-compatible schema.');
        }

        $this->seed(\Database\Seeders\GuardSeeder::class);
        $this->seed(\Database\Seeders\ReservationPermissionsSeeder::class);

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
            'number' => '101',
            'name' => 'Habitación 101',
            'status' => 'available',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 200000,
        ]);

        $this->maintenanceRoom = Room::create([
            'room_type_id' => $this->roomType->id,
            'number' => '102',
            'name' => 'Habitación 102',
            'status' => 'maintenance',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 200000,
        ]);

        $this->customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '1002003001',
            'name' => 'Carlos',
            'last_name' => 'Ospina',
            'email' => 'carlos.ospina@test.com',
            'active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Reportes',
            'email' => 'admin-reportes@test.com',
            'password' => Hash::make('password'),
            'active' => true,
        ]);

        $role = Role::create(['name' => 'report_test_admin', 'guard_name' => 'reservas']);
        $role->givePermissionTo('reservation.report');

        DB::table('user_has_roles')->insert([
            'role_id' => $role->id,
            'user_id' => $this->admin->id,
            'model_type' => User::class,
        ]);

        Sanctum::actingAs($this->admin);
    }

    protected function createRoomReservation(Room $room, string $checkIn, string $checkOut, string $status = 'confirmed'): Reservation
    {
        return Reservation::create([
            'customer_id' => $this->customer->id,
            'room_id' => $room->id,
            'room_type_id' => $this->roomType->id,
            'reservation_type' => 'room',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'total_price' => 1000000,
            'status' => $status,
        ]);
    }

    public function test_report_includes_reservation_that_started_before_range(): void
    {
        $base = now()->addDays(10);
        $reservation = $this->createRoomReservation(
            $this->room,
            $base->format('Y-m-d'),
            $base->copy()->addDays(5)->format('Y-m-d')
        );

        $from = $base->copy()->addDays(2)->format('Y-m-d');
        $to = $base->copy()->addDays(4)->format('Y-m-d');

        $response = $this->getJson("/api/reservations/room-occupancy/report?date_from={$from}&date_to={$to}");

        $response->assertOk();

        $roomRow = collect($response->json('rooms'))->firstWhere('id', $this->room->id);
        $this->assertNotNull($roomRow);
        $this->assertCount(3, $roomRow['dates']);

        foreach ($roomRow['dates'] as $cell) {
            $this->assertSame('occupied', $cell['status']);
            $this->assertSame($reservation->reservation_number, $cell['reservation']['reservation_number']);
            $this->assertSame('Carlos Ospina', $cell['reservation']['customer_name']);
        }

        $occupancy = collect($response->json('occupancy_by_date'));
        $this->assertCount(3, $occupancy);
        foreach ($occupancy as $day) {
            $this->assertSame(2, $day['total_rooms']);
            $this->assertSame(1, $day['occupied_rooms']);
            $this->assertSame(0, $day['available_rooms']);
            $this->assertSame(1, $day['maintenance_rooms']);
            $this->assertEquals(50, $day['occupancy_percentage']);
        }
    }

    public function test_report_marks_check_out_day_as_available(): void
    {
        $base = now()->addDays(20);
        $this->createRoomReservation(
            $this->room,
            $base->format('Y-m-d'),
            $base->copy()->addDays(2)->format('Y-m-d')
        );

        $from = $base->format('Y-m-d');
        $to = $base->copy()->addDays(2)->format('Y-m-d');

        $response = $this->getJson("/api/reservations/room-occupancy/report?date_from={$from}&date_to={$to}");

        $response->assertOk();

        $roomRow = collect($response->json('rooms'))->firstWhere('id', $this->room->id);
        $this->assertCount(3, $roomRow['dates']);

        $this->assertSame('occupied', $roomRow['dates'][0]['status']);
        $this->assertSame('occupied', $roomRow['dates'][1]['status']);
        $this->assertSame('available', $roomRow['dates'][2]['status']);
        $this->assertNull($roomRow['dates'][2]['reservation']);
    }

    public function test_report_marks_room_in_maintenance(): void
    {
        $base = now()->addDays(30);

        $response = $this->getJson("/api/reservations/room-occupancy/report?date_from={$base->format('Y-m-d')}&date_to={$base->copy()->addDays(1)->format('Y-m-d')}");

        $response->assertOk();

        $roomRow = collect($response->json('rooms'))->firstWhere('id', $this->maintenanceRoom->id);
        $this->assertNotNull($roomRow);

        foreach ($roomRow['dates'] as $cell) {
            $this->assertSame('maintenance', $cell['status']);
            $this->assertNull($cell['reservation']);
        }
    }

    public function test_report_requires_both_dates(): void
    {
        $response = $this->getJson('/api/reservations/room-occupancy/report?date_from=2026-08-01');

        $response->assertStatus(422);
    }

    public function test_legacy_occupancy_report_includes_overlapping_reservation(): void
    {
        $base = now()->addDays(10);
        $this->createRoomReservation(
            $this->room,
            $base->format('Y-m-d'),
            $base->copy()->addDays(5)->format('Y-m-d')
        );

        $from = $base->copy()->addDays(2)->format('Y-m-d');
        $to = $base->copy()->addDays(3)->format('Y-m-d');

        $response = $this->getJson("/api/reservations/occupancy/report?date_from={$from}&date_to={$to}");

        $response->assertOk()
            ->assertJsonPath('summary.total_reservations', 1);

        $occupancy = collect($response->json('occupancy_by_date'));
        $this->assertCount(2, $occupancy);

        foreach ($occupancy as $day) {
            $this->assertSame(1, $day['rooms_occupied']);
        }
    }
}
