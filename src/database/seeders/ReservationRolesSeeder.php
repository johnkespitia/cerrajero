<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ReservationRolesSeeder extends Seeder
{
    /**
     * Guard name para el módulo de reservas
     */
    private const GUARD = 'reservas';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando roles del módulo Reservas...');

        // 1. Administrador de Reservas
        $this->createAdminRole();

        // 2. Recepcionista
        $this->createReceptionistRole();

        // 3. Agente de Reservas
        $this->createAgentRole();

        // 4. Consultor de Reservas
        $this->createConsultantRole();

        // 5. Analista de Marketing
        $this->createMarketingRole();

        $this->command->info('Roles de Reservas creados exitosamente.');
    }

    /**
     * Crear rol Administrador de Reservas
     */
    private function createAdminRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'reservas_admin', 'guard_name' => self::GUARD],
            ['name' => 'reservas_admin', 'guard_name' => self::GUARD]
        );

        $role->syncPermissions([
            // Permisos de reservas
            'reservation.list',
            'reservation.create',
            'reservation.edit',
            'reservation.delete',
            'reservation.view',
            'reservation.report',
            // Permisos de habitaciones
            'room.list',
            'room.view',
            // Permisos de tipos de habitación
            'room_type.list',
            'room_type.view',
        ]);

        $this->command->info('  ✓ Rol reservas_admin creado');
    }

    /**
     * Crear rol Recepcionista
     */
    private function createReceptionistRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'recepcionista', 'guard_name' => self::GUARD],
            ['name' => 'recepcionista', 'guard_name' => self::GUARD]
        );

        $role->syncPermissions([
            // Permisos de reservas (sin delete ni report)
            'reservation.list',
            'reservation.create',
            'reservation.edit',
            'reservation.view',
            // Permisos de habitaciones
            'room.list',
            'room.view',
            // Permisos de tipos de habitación
            'room_type.list',
            'room_type.view',
        ]);

        $this->command->info('  ✓ Rol recepcionista creado');
    }

    /**
     * Crear rol Agente de Reservas
     */
    private function createAgentRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'agente_reservas', 'guard_name' => self::GUARD],
            ['name' => 'agente_reservas', 'guard_name' => self::GUARD]
        );

        $role->syncPermissions([
            // Permisos de reservas (solo crear y ver)
            'reservation.list',
            'reservation.create',
            'reservation.view',
            // Permisos de habitaciones
            'room.list',
            'room.view',
            // Permisos de tipos de habitación
            'room_type.list',
            'room_type.view',
        ]);

        $this->command->info('  ✓ Rol agente_reservas creado');
    }

    /**
     * Crear rol Consultor de Reservas
     */
    private function createConsultantRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'consultor_reservas', 'guard_name' => self::GUARD],
            ['name' => 'consultor_reservas', 'guard_name' => self::GUARD]
        );

        $role->syncPermissions([
            // Permisos de reservas (solo lectura)
            'reservation.list',
            'reservation.view',
            // Permisos de habitaciones
            'room.list',
            'room.view',
            // Permisos de tipos de habitación
            'room_type.list',
            'room_type.view',
        ]);

        $this->command->info('  ✓ Rol consultor_reservas creado');
    }

    /**
     * Crear rol Analista de Marketing
     */
    private function createMarketingRole(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'analista_marketing', 'guard_name' => self::GUARD],
            ['name' => 'analista_marketing', 'guard_name' => self::GUARD]
        );

        $role->syncPermissions([
            // Permisos de reservas (con reportes)
            'reservation.list',
            'reservation.view',
            'reservation.report',
            // Permisos de habitaciones
            'room.list',
            'room.view',
            // Permisos de tipos de habitación
            'room_type.list',
            'room_type.view',
        ]);

        $this->command->info('  ✓ Rol analista_marketing creado');
    }
}

