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

        // Permisos de inventario de habitaciones
        $this->createRoomInventoryPermissions();

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

    /**
     * Crear permisos de inventario de habitaciones y zonas comunes
     */
    private function createRoomInventoryPermissions(): void
    {
        // Permisos principales
        $permissions = [
            'room_inventory.list' => 'Ver inventario de habitaciones',
        ];

        // Permisos de categorías
        $categoryPermissions = [
            'room_inventory.category.list' => 'Ver categorías de inventario',
            'room_inventory.category.create' => 'Crear categorías de inventario',
            'room_inventory.category.edit' => 'Editar categorías de inventario',
            'room_inventory.category.delete' => 'Eliminar categorías de inventario',
        ];

        // Permisos de artículos
        $itemPermissions = [
            'room_inventory.item.list' => 'Ver artículos de inventario',
            'room_inventory.item.create' => 'Crear artículos de inventario',
            'room_inventory.item.edit' => 'Editar artículos de inventario',
            'room_inventory.item.delete' => 'Eliminar artículos de inventario',
        ];

        // Permisos de zonas comunes
        $commonAreaPermissions = [
            'room_inventory.common_area.list' => 'Ver zonas comunes',
            'room_inventory.common_area.create' => 'Crear zonas comunes',
            'room_inventory.common_area.edit' => 'Editar zonas comunes',
            'room_inventory.common_area.delete' => 'Eliminar zonas comunes',
        ];

        // Permisos de asignaciones
        $assignmentPermissions = [
            'room_inventory.assignment.list' => 'Ver asignaciones de inventario',
            'room_inventory.assignment.create' => 'Crear asignaciones de inventario',
            'room_inventory.assignment.edit' => 'Editar asignaciones de inventario',
            'room_inventory.assignment.delete' => 'Eliminar asignaciones de inventario',
        ];

        // Permisos de historial
        $historyPermissions = [
            'room_inventory.history.view' => 'Ver historial de inventario',
        ];

        // Combinar todos los permisos
        $allPermissions = array_merge(
            $permissions,
            $categoryPermissions,
            $itemPermissions,
            $commonAreaPermissions,
            $assignmentPermissions,
            $historyPermissions
        );

        foreach ($allPermissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de inventario de habitaciones creados');
    }
}

