<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ReservationPermissionsSeeder extends Seeder
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
        $this->command->info('Creando permisos del módulo Reservas...');

        // Permisos principales de reservas
        $this->createReservationPermissions();

        // Permisos de habitaciones (dependencia)
        $this->createRoomPermissions();

        // Permisos de tipos de habitación (dependencia)
        $this->createRoomTypePermissions();

        // Permisos de clientes (dependencia)
        $this->createCustomerPermissions();

        $this->command->info('Permisos de Reservas creados exitosamente.');
    }

    /**
     * Crear permisos principales de reservas
     */
    private function createReservationPermissions(): void
    {
        $permissions = [
            'reservation.list' => 'Listar reservas',
            'reservation.create' => 'Crear reservas',
            'reservation.edit' => 'Editar reservas',
            'reservation.delete' => 'Eliminar reservas',
            'reservation.view' => 'Ver detalles y certificados de reservas',
            'reservation.report' => 'Ver reportes de marketing de reservas',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de reservas creados');
    }

    /**
     * Crear permisos de habitaciones
     */
    private function createRoomPermissions(): void
    {
        $permissions = [
            'room.list' => 'Listar habitaciones',
            'room.create' => 'Crear habitaciones',
            'room.edit' => 'Editar habitaciones',
            'room.delete' => 'Eliminar habitaciones',
            'room.view' => 'Ver detalles de habitación',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de habitaciones creados');
    }

    /**
     * Crear permisos de tipos de habitación
     */
    private function createRoomTypePermissions(): void
    {
        $permissions = [
            'room_type.list' => 'Listar tipos de habitación',
            'room_type.create' => 'Crear tipos de habitación',
            'room_type.edit' => 'Editar tipos de habitación',
            'room_type.delete' => 'Eliminar tipos de habitación',
            'room_type.view' => 'Ver detalles de tipo de habitación',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de tipos de habitación creados');
    }

    /**
     * Crear permisos de clientes
     */
    private function createCustomerPermissions(): void
    {
        $permissions = [
            'customer.list' => 'Listar y buscar clientes',
            'customer.create' => 'Crear clientes',
            'customer.edit' => 'Editar clientes',
            'customer.view' => 'Ver detalles de cliente',
            'customer.delete' => 'Eliminar clientes',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de clientes creados');
    }
}

