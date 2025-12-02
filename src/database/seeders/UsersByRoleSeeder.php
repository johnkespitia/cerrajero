<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UsersByRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando usuarios para cada rol del sistema...');

        // Obtener todos los roles
        $roles = Role::all();

        foreach ($roles as $role) {
            $this->createUserForRole($role);
        }

        $this->command->info('Usuarios creados exitosamente para todos los roles.');
    }

    /**
     * Crear un usuario para un rol específico
     *
     * @param Role $role
     * @return void
     */
    private function createUserForRole(Role $role): void
    {
        // Generar nombre de usuario basado en el nombre del rol
        $username = $this->generateUsername($role->name);
        $email = $this->generateEmail($role->name, $role->guard_name);

        // Crear o actualizar usuario
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $this->generateDisplayName($role->name, $role->guard_name),
                'email' => $email,
                'password' => Hash::make('1234567890'), // Password por defecto
                'active' => true,
            ]
        );

        // Asignar rol si no lo tiene
        // Verificar si el usuario ya tiene este rol usando la tabla user_has_roles
        $hasRole = DB::table('user_has_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();

        if (!$hasRole) {
            // Asignar rol directamente usando la tabla pivot
            // La tabla user_has_roles requiere: role_id, user_id, model_type
            DB::table('user_has_roles')->insert([
                'role_id' => $role->id,
                'user_id' => $user->id,
                'model_type' => get_class($user),
            ]);
            $this->command->info("  ✓ Usuario '{$user->name}' creado/actualizado con rol '{$role->name}' ({$role->guard_name})");
        } else {
            $this->command->info("  - Usuario '{$user->name}' ya tiene el rol '{$role->name}' ({$role->guard_name})");
        }
    }

    /**
     * Generar nombre de usuario a partir del nombre del rol
     *
     * @param string $roleName
     * @return string
     */
    private function generateUsername(string $roleName): string
    {
        // Convertir nombre de rol a formato de usuario
        $username = str_replace('_', '.', $roleName);
        return strtolower($username);
    }

    /**
     * Generar email a partir del nombre del rol y guard
     *
     * @param string $roleName
     * @param string $guardName
     * @return string
     */
    private function generateEmail(string $roleName, string $guardName): string
    {
        $username = $this->generateUsername($roleName);
        return "{$username}@campoverde.demo";
    }

    /**
     * Generar nombre para mostrar
     *
     * @param string $roleName
     * @param string $guardName
     * @return string
     */
    private function generateDisplayName(string $roleName, string $guardName): string
    {
        // Convertir snake_case a Title Case
        $name = str_replace('_', ' ', $roleName);
        $name = ucwords($name);
        
        // Agregar módulo si es relevante
        $moduleNames = [
            'cerrajero' => 'Sistema',
            'restbodega' => 'Bodega',
            'restcocina' => 'Cocina',
            'restcaja' => 'Caja Restaurante',
            'kioskinvetario' => 'Inventario Kiosko',
            'kioskcaja' => 'Caja Kiosko',
            'clientes' => 'Clientes',
            'reservas' => 'Reservas',
        ];

        $moduleName = $moduleNames[$guardName] ?? ucfirst($guardName);
        
        return "Usuario {$name} - {$moduleName}";
    }
}

