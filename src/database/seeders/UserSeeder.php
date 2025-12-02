<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear usuario administrador root
        $user = User::firstOrCreate(
            ['email' => 'jcespitia1@demo.com'],
            [
                'name' => 'ADMINISTRATOR',
                'email' => 'jcespitia1@demo.com',
                'password' => Hash::make('1234567890'),
                'active' => true
            ]
        );

        // Asignar rol root si existe
        try {
            $rootRole = Role::findByName('root', 'cerrajero');
            
            if (!$user->hasRole($rootRole)) {
                $user->assignRole($rootRole);
                $this->command->info('Usuario administrador creado y rol root asignado');
            } else {
                $this->command->info('Usuario administrador ya tiene el rol root asignado');
            }
        } catch (\Exception $e) {
            $this->command->warn('Rol root no encontrado. Asegúrate de ejecutar RolSeeder primero.');
        }
    }
}
