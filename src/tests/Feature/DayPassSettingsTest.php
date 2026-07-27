<?php

namespace Tests\Feature;

use App\Models\DayPassCapacity;
use App\Models\ReservationSetting;
use App\Models\User;
use App\Services\DayPassSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DayPassSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Day pass settings tests require MySQL permissions schema.');
        }

        $this->seed(\Database\Seeders\GuardSeeder::class);
        $this->seed(\Database\Seeders\ReservationPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ReservationRolesSeeder::class);
    }

    public function test_defaults_fall_back_to_config_values(): void
    {
        config([
            'day_pass.default_capacity' => 600,
            'day_pass.default_adult_price' => 20000,
            'day_pass.default_child_price' => 20000,
        ]);

        $defaults = app(DayPassSettingsService::class)->defaults();

        $this->assertSame(600, $defaults['default_capacity']);
        $this->assertSame(20000.0, $defaults['default_adult_price']);
        $this->assertSame(20000.0, $defaults['default_child_price']);
    }

    public function test_get_or_create_for_date_uses_saved_defaults(): void
    {
        ReservationSetting::set('day_pass_default_capacity', '600');
        ReservationSetting::set('day_pass_default_adult_price', '20000');
        ReservationSetting::set('day_pass_default_child_price', '20000');

        $capacity = DayPassCapacity::getOrCreateForDate('2026-08-01', 0, 0, 0);

        $this->assertSame(600, $capacity->max_capacity);
        $this->assertSame('20000.00', $capacity->adult_price);
        $this->assertSame('20000.00', $capacity->child_price);
    }

    public function test_admin_can_update_day_pass_defaults(): void
    {
        Sanctum::actingAs($this->createReservationAdmin());

        $response = $this->putJson('/api/day-pass-settings', [
            'default_capacity' => 650,
            'default_adult_price' => 25000,
            'default_child_price' => 25000,
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.default_capacity', 650)
            ->assertJsonPath('settings.default_adult_price', 25000)
            ->assertJsonPath('settings.default_child_price', 25000);

        $this->assertSame('650', ReservationSetting::get('day_pass_default_capacity'));
    }

    private function createReservationAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Pasadía',
            'email' => 'admin-day-pass@test.com',
            'password' => Hash::make('password'),
            'active' => true,
        ]);

        $role = Role::where('name', 'reservas_admin')->where('guard_name', 'reservas')->firstOrFail();
        DB::table('user_has_roles')->insert([
            'role_id' => $role->id,
            'user_id' => $admin->id,
            'model_type' => User::class,
        ]);

        return $admin;
    }
}
