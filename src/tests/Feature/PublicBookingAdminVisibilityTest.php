<?php

namespace Tests\Feature;

use App\Models\ReservationSetting;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicBookingAdminVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Public booking admin tests require MySQL permissions schema.');
        }

        $this->seed(\Database\Seeders\GuardSeeder::class);
        $this->seed(\Database\Seeders\ReservationPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ReservationRolesSeeder::class);

        ReservationSetting::set('web_booking_enabled', 'true');
        ReservationSetting::set('web_payment_mode_request_enabled', 'true');
        ReservationSetting::set('web_payment_mode_deposit_enabled', 'true');
        ReservationSetting::set('web_payment_mode_full_enabled', 'true');
        ReservationSetting::set('web_deposit_percentage', '30');

        $roomType = RoomType::create([
            'name' => 'Estándar',
            'code' => 'EST',
            'default_capacity' => 2,
            'max_capacity' => 2,
            'base_price' => 180000,
            'active' => true,
        ]);

        Room::create([
            'room_type_id' => $roomType->id,
            'number' => '101',
            'name' => 'Habitación 101',
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 180000,
            'status' => 'available',
            'active' => true,
        ]);
    }

    public function test_web_reservation_appears_in_admin_list_and_detail(): void
    {
        $publicResponse = $this->postJson('/api/public/booking/reservations', [
            'reservation_type' => 'room',
            'check_in_date' => now()->addDays(10)->format('Y-m-d'),
            'check_out_date' => now()->addDays(12)->format('Y-m-d'),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'room_type_id' => 1,
            'payment_mode' => 'request_only',
            'customer' => [
                'customer_type' => 'person',
                'name' => 'Ana',
                'last_name' => 'Web',
                'email' => 'ana.web@test.com',
                'phone' => '3001112233',
                'dni' => 'WEBADMIN001',
            ],
        ]);

        $publicResponse->assertCreated();
        $reservationId = $publicResponse->json('reservation.id');

        $admin = User::create([
            'name' => 'Admin Reservas',
            'email' => 'admin-reservas@test.com',
            'password' => Hash::make('password'),
            'active' => true,
        ]);

        $role = Role::where('name', 'reservas_admin')->where('guard_name', 'reservas')->firstOrFail();
        DB::table('user_has_roles')->insert([
            'role_id' => $role->id,
            'user_id' => $admin->id,
            'model_type' => User::class,
        ]);

        Sanctum::actingAs($admin);

        $listResponse = $this->getJson('/api/reservations?status=pending&web_booking_only=1');
        $listResponse->assertOk();

        $ids = collect($listResponse->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($reservationId));

        $showResponse = $this->getJson("/api/reservations/{$reservationId}");
        $showResponse->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('contact_channel', 'website')
            ->assertJsonPath('web_payment_mode', 'request_only')
            ->assertJsonPath('payment_status', 'pending');
    }
}
