<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class MinibarPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de minibar (usando el mismo guard que reservas)
     */
    private const GUARD = 'reservas';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo Minibar...');

        // Permisos principales
        $this->createMainPermissions();

        // Permisos de categorías
        $this->createCategoryPermissions();

        // Permisos de productos
        $this->createProductPermissions();

        // Permisos de inventario
        $this->createInventoryPermissions();

        // Permisos de cargos
        $this->createChargePermissions();

        $this->command->info('Permisos de Minibar creados exitosamente.');
    }

    /**
     * Crear permisos principales
     */
    private function createMainPermissions(): void
    {
        $permissions = [
            'minibar.list' => 'Ver módulo de minibar',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos principales creados');
    }

    /**
     * Crear permisos de categorías
     */
    private function createCategoryPermissions(): void
    {
        $permissions = [
            'minibar.category.list' => 'Ver categorías de productos del minibar',
            'minibar.category.create' => 'Crear categorías de productos del minibar',
            'minibar.category.edit' => 'Editar categorías de productos del minibar',
            'minibar.category.delete' => 'Eliminar categorías de productos del minibar',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de categorías creados');
    }

    /**
     * Crear permisos de productos
     */
    private function createProductPermissions(): void
    {
        $permissions = [
            'minibar.product.list' => 'Ver productos del minibar',
            'minibar.product.create' => 'Crear productos del minibar',
            'minibar.product.edit' => 'Editar productos del minibar',
            'minibar.product.delete' => 'Eliminar productos del minibar',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de productos creados');
    }

    /**
     * Crear permisos de inventario
     */
    private function createInventoryPermissions(): void
    {
        $permissions = [
            'minibar.inventory.view' => 'Ver inventario del minibar',
            'minibar.inventory.record' => 'Registrar inventario del minibar (check-in, limpieza, checkout)',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de inventario creados');
    }

    /**
     * Crear permisos de cargos
     */
    private function createChargePermissions(): void
    {
        $permissions = [
            'minibar.charge.view' => 'Ver cargos del minibar',
            'minibar.charge.delete' => 'Eliminar cargos del minibar',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de cargos creados');
    }
}
