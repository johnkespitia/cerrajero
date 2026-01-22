<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class CleaningMaintenancePermissionsSeeder extends Seeder
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
        $this->command->info('Creando permisos del módulo Aseo y Mantenimiento...');

        // Permisos de Aseo
        $this->createCleaningPermissions();

        // Permisos de Mantenimiento
        $this->createMaintenancePermissions();

        $this->command->info('Permisos de Aseo y Mantenimiento creados exitosamente.');
    }

    /**
     * Crear permisos del módulo de Aseo
     */
    private function createCleaningPermissions(): void
    {
        $permissions = [
            'cleaning.list' => 'Listar registros de aseo',
            'cleaning.create' => 'Crear registros de aseo',
            'cleaning.edit' => 'Editar registros de aseo',
            'cleaning.schedule' => 'Gestionar programación de aseo',
            'cleaning.report' => 'Ver reportes y estadísticas de aseo',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de aseo creados');
    }

    /**
     * Crear permisos del módulo de Mantenimiento
     */
    private function createMaintenancePermissions(): void
    {
        // Permisos principales
        $permissions = [
            'maintenance.list' => 'Listar solicitudes y trabajos de mantenimiento',
            'maintenance.report' => 'Ver reportes y estadísticas de mantenimiento',
        ];

        // Permisos de solicitudes de mantenimiento
        $requestPermissions = [
            'maintenance.request.create' => 'Crear solicitudes de mantenimiento',
            'maintenance.request.edit' => 'Editar solicitudes de mantenimiento',
            'maintenance.request.assign' => 'Asignar solicitudes de mantenimiento',
        ];

        // Permisos de trabajos de mantenimiento
        $workPermissions = [
            'maintenance.work.create' => 'Registrar trabajos de mantenimiento',
            'maintenance.work.edit' => 'Editar trabajos de mantenimiento',
        ];

        // Permisos de proveedores
        $supplierPermissions = [
            'maintenance.supplier.manage' => 'Gestionar proveedores de mantenimiento',
        ];

        // Combinar todos los permisos
        $allPermissions = array_merge(
            $permissions,
            $requestPermissions,
            $workPermissions,
            $supplierPermissions
        );

        foreach ($allPermissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de mantenimiento creados');
        $this->command->info('  Total de permisos creados: ' . count($allPermissions));
    }
}
