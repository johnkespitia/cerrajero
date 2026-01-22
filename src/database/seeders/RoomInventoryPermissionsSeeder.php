<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoomInventoryPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de reservas (asociado al módulo existente)
     */
    private const GUARD = 'reservas';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo Inventario de Habitaciones...');

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

        $this->command->info('  ✓ Permisos de inventario de habitaciones creados exitosamente.');
        $this->command->info('  Total de permisos creados: ' . count($allPermissions));
    }
}
