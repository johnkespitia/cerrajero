<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RestBodegaPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de bodega
     */
    private const GUARD = 'restbodega';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo RestBodega...');

        // Categorías de inventario
        $this->createCategoryPermissions();

        // Tipos de entrada de inventario
        $this->createInventoryTypePermissions();

        // Productos de inventario
        $this->createInputPermissions();

        // Lotes de inventario
        $this->createBatchPermissions();

        // Medidas de inventario
        $this->createMeasuresPermissions();

        $this->command->info('Permisos de RestBodega creados exitosamente.');
    }

    private function createCategoryPermissions(): void
    {
        $permissions = [
            'category.list' => 'Listar categorías de inventario',
            'category.create' => 'Crear categorías de inventario',
            'category.edit' => 'Editar categorías de inventario',
            'category.delete' => 'Eliminar categorías de inventario',
            'category.view' => 'Ver detalles de categoría',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de categorías creados');
    }

    private function createInventoryTypePermissions(): void
    {
        $permissions = [
            'inventory-type.list' => 'Listar tipos de entrada',
            'inventory-type.create' => 'Crear tipos de entrada',
            'inventory-type.edit' => 'Editar tipos de entrada',
            'inventory-type.delete' => 'Eliminar tipos de entrada',
            'inventory-type.view' => 'Ver detalles de tipo de entrada',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de tipos de entrada creados');
    }

    private function createInputPermissions(): void
    {
        $permissions = [
            'input.list' => 'Listar productos de inventario',
            'input.create' => 'Crear productos de inventario',
            'input.edit' => 'Editar productos de inventario',
            'input.delete' => 'Eliminar productos de inventario',
            'input.view' => 'Ver detalles de producto',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de productos creados');
    }

    private function createBatchPermissions(): void
    {
        $permissions = [
            'batch.list' => 'Listar lotes de inventario',
            'batch.create' => 'Crear lotes de inventario',
            'batch.edit' => 'Editar lotes de inventario',
            'batch.delete' => 'Eliminar lotes de inventario',
            'batch.view' => 'Ver detalles de lote',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de lotes creados');
    }

    private function createMeasuresPermissions(): void
    {
        $permissions = [
            'measures.list' => 'Listar medidas de inventario',
            'measures.create' => 'Crear medidas de inventario',
            'measures.edit' => 'Editar medidas de inventario',
            'measures.delete' => 'Eliminar medidas de inventario',
            'measures.view' => 'Ver detalles de medida',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de medidas creados');
    }
}

