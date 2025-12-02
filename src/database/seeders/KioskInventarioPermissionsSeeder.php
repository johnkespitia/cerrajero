<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class KioskInventarioPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de inventario del kiosko
     */
    private const GUARD = 'kioskinvetario';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo KioskInventario...');

        // Categorías del kiosko
        $this->createCategoryPermissions();

        // Productos del kiosko
        $this->createProductPermissions();

        $this->command->info('Permisos de KioskInventario creados exitosamente.');
    }

    private function createCategoryPermissions(): void
    {
        $permissions = [
            'kiosk_categories.list' => 'Listar categorías del kiosko',
            'kiosk_categories.create' => 'Crear categorías del kiosko',
            'kiosk_categories.edit' => 'Editar categorías del kiosko',
            'kiosk_categories.delete' => 'Eliminar categorías del kiosko',
            'kiosk_categories.view' => 'Ver detalles de categoría',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de categorías del kiosko creados');
    }

    private function createProductPermissions(): void
    {
        $permissions = [
            'kiosk_products.list' => 'Listar productos del kiosko',
            'kiosk_products.create' => 'Crear productos del kiosko',
            'kiosk_products.edit' => 'Editar productos del kiosko',
            'kiosk_products.delete' => 'Eliminar productos del kiosko',
            'kiosk_products.view' => 'Ver detalles de producto',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de productos del kiosko creados');
    }
}

