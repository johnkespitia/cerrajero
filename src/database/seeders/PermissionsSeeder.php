<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Guard name por defecto para permisos del sistema
     */
    private const DEFAULT_GUARD = 'cerrajero';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del sistema...');

        // Módulo de Usuarios
        $this->createUserPermissions();

        // Módulo de Roles
        $this->createRolePermissions();

        // Módulo de Permisos
        $this->createPermissionPermissions();

        // Módulo de Guards
        $this->createGuardPermissions();

        $this->command->info('Permisos creados exitosamente.');
    }

    /**
     * Crear permisos del módulo de Usuarios
     */
    private function createUserPermissions(): void
    {
        $permissions = [
            'user.list' => 'Listar usuarios',
            'user.create' => 'Crear usuarios',
            'user.edit' => 'Editar usuarios',
            'user.delete' => 'Eliminar usuarios',
            'user.view' => 'Ver detalles de usuario',
            'user.assign_role' => 'Asignar roles a usuarios',
            'user.remove_role' => 'Remover roles de usuarios',
            'user.assign_superior' => 'Asignar superior a usuario',
            'user.remove_superior' => 'Remover superior de usuario',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD],
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de usuarios creados');
    }

    /**
     * Crear permisos del módulo de Roles
     */
    private function createRolePermissions(): void
    {
        $permissions = [
            'role.list' => 'Listar roles',
            'role.create' => 'Crear roles',
            'role.edit' => 'Editar roles',
            'role.delete' => 'Eliminar roles',
            'role.view' => 'Ver detalles de rol',
            'role.grant_permission' => 'Otorgar permisos a roles',
            'role.revoke_permission' => 'Revocar permisos de roles',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD],
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de roles creados');
    }

    /**
     * Crear permisos del módulo de Permisos
     */
    private function createPermissionPermissions(): void
    {
        $permissions = [
            'permission.list' => 'Listar permisos',
            'permission.create' => 'Crear permisos',
            'permission.edit' => 'Editar permisos',
            'permission.delete' => 'Eliminar permisos',
            'permission.view' => 'Ver detalles de permiso',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD],
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de permisos creados');
    }

    /**
     * Crear permisos del módulo de Guards
     */
    private function createGuardPermissions(): void
    {
        $permissions = [
            'guard.list' => 'Listar guards',
            'guard.create' => 'Crear guards',
            'guard.edit' => 'Editar guards',
            'guard.delete' => 'Eliminar guards',
            'guard.view' => 'Ver detalles de guard',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD],
                ['name' => $name, 'guard_name' => self::DEFAULT_GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de guards creados');
    }
}
