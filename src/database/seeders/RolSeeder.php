<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolSeeder extends Seeder
{
    /**
     * Guard name por defecto
     */
    private const DEFAULT_GUARD = 'cerrajero';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando roles del sistema...');

        // Rol Root - Acceso completo
        $this->createRootRole();

        // Rol Administrador - Gestión completa de usuarios y permisos
        $this->createAdministratorRole();

        // Rol Supervisor - Gestión limitada
        $this->createSupervisorRole();

        $this->command->info('Roles creados exitosamente.');
    }

    /**
     * Crear rol Root con todos los permisos
     */
    private function createRootRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => self::DEFAULT_GUARD],
            ['name' => 'root', 'guard_name' => self::DEFAULT_GUARD]
        );

        // El rol root debe tener acceso a todos los permisos del sistema
        // Sin embargo, Spatie Permission solo permite permisos del mismo guard
        // Por lo tanto, asignamos todos los permisos del guard cerrajero
        // Los permisos de otros guards se manejan a través de roles específicos de esos guards
        
        $cerrajeroPermissions = \Spatie\Permission\Models\Permission::where('guard_name', 'cerrajero')
            ->pluck('name')
            ->toArray();

        $role->syncPermissions($cerrajeroPermissions);
        $this->command->info('  ✓ Rol root creado con todos los permisos del módulo cerrajero');
        $this->command->info('    Nota: Para acceso completo a otros módulos, asigne roles adicionales de esos guards');
    }

    /**
     * Crear rol Administrador con permisos de gestión
     */
    private function createAdministratorRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'administrator', 'guard_name' => self::DEFAULT_GUARD],
            ['name' => 'administrator', 'guard_name' => self::DEFAULT_GUARD]
        );

        $permissions = [
            // Usuarios
            'user.list',
            'user.create',
            'user.edit',
            'user.view',
            'user.assign_role',
            'user.remove_role',
            // Roles
            'role.list',
            'role.view',
            // Permisos
            'permission.list',
            'permission.view',
        ];

        $role->syncPermissions($permissions);
        $this->command->info('  ✓ Rol administrator creado');
    }

    /**
     * Crear rol Supervisor con permisos limitados
     */
    private function createSupervisorRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'supervisor', 'guard_name' => self::DEFAULT_GUARD],
            ['name' => 'supervisor', 'guard_name' => self::DEFAULT_GUARD]
        );

        $permissions = [
            // Usuarios
            'user.list',
            'user.view',
            // Roles
            'role.list',
            'role.view',
            // Permisos
            'permission.list',
            'permission.view',
        ];

        $role->syncPermissions($permissions);
        $this->command->info('  ✓ Rol supervisor creado');
    }
}
