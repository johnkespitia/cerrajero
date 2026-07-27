<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DevBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GuardSeeder::class);
        $this->call(ReservationPermissionsSeeder::class);
        $this->call(ReservationRolesSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'jcespitia1@demo.com'],
            [
                'name' => 'ADMINISTRATOR',
                'password' => Hash::make('1234567890'),
                'active' => true,
            ]
        );

        $this->assignRoleViaPivot($admin, 'reservas_admin', 'reservas');

        $this->command?->info('DevBootstrapSeeder: usuario demo con rol reservas_admin listo.');
    }

    private function assignRoleViaPivot(User $user, string $roleName, string $guardName): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guardName)->first();
        if (!$role) {
            $this->command?->warn("DevBootstrapSeeder: rol {$roleName} ({$guardName}) no encontrado.");

            return;
        }

        $exists = DB::table('user_has_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();

        if (!$exists) {
            DB::table('user_has_roles')->insert([
                'role_id' => $role->id,
                'user_id' => $user->id,
                'model_type' => User::class,
            ]);
        }
    }
}
