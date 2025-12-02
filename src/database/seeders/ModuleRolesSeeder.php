<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ModuleRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando roles por módulo...');

        // Roles para RestBodega
        $this->createRestBodegaRoles();

        // Roles para RestCocina
        $this->createRestCocinaRoles();

        // Roles para RestCaja
        $this->createRestCajaRoles();

        // Roles para KioskInventario
        $this->createKioskInventarioRoles();

        // Roles para KioskCaja
        $this->createKioskCajaRoles();

        // Roles para Clientes
        $this->createClientesRoles();

        $this->command->info('Roles por módulo creados exitosamente.');
    }

    private function createRestBodegaRoles(): void
    {
        $guard = 'restbodega';

        // Administrador de Bodega
        $admin = Role::firstOrCreate(
            ['name' => 'bodega_admin', 'guard_name' => $guard],
            ['name' => 'bodega_admin', 'guard_name' => $guard]
        );
        $admin->syncPermissions([
            'category.list', 'category.create', 'category.edit', 'category.delete', 'category.view',
            'inventory-type.list', 'inventory-type.create', 'inventory-type.edit', 'inventory-type.delete', 'inventory-type.view',
            'input.list', 'input.create', 'input.edit', 'input.delete', 'input.view',
            'batch.list', 'batch.create', 'batch.edit', 'batch.delete', 'batch.view',
            'measures.list', 'measures.create', 'measures.edit', 'measures.delete', 'measures.view',
        ]);
        $this->command->info('  ✓ Rol bodega_admin creado');

        // Operador de Bodega
        $operador = Role::firstOrCreate(
            ['name' => 'bodega_operador', 'guard_name' => $guard],
            ['name' => 'bodega_operador', 'guard_name' => $guard]
        );
        $operador->syncPermissions([
            'category.list', 'category.view',
            'inventory-type.list', 'inventory-type.view',
            'input.list', 'input.create', 'input.edit', 'input.view',
            'batch.list', 'batch.create', 'batch.edit', 'batch.view',
            'measures.list', 'measures.view',
        ]);
        $this->command->info('  ✓ Rol bodega_operador creado');
    }

    private function createRestCocinaRoles(): void
    {
        $guard = 'restcocina';

        // Chef
        $chef = Role::firstOrCreate(
            ['name' => 'chef', 'guard_name' => $guard],
            ['name' => 'chef', 'guard_name' => $guard]
        );
        $chef->syncPermissions([
            'recipes.list', 'recipes.create', 'recipes.edit', 'recipes.delete', 'recipes.view',
        ]);
        $this->command->info('  ✓ Rol chef creado');

        // Ayudante de Cocina
        $ayudante = Role::firstOrCreate(
            ['name' => 'ayudante_cocina', 'guard_name' => $guard],
            ['name' => 'ayudante_cocina', 'guard_name' => $guard]
        );
        $ayudante->syncPermissions([
            'recipes.list', 'recipes.view',
        ]);
        $this->command->info('  ✓ Rol ayudante_cocina creado');
    }

    private function createRestCajaRoles(): void
    {
        $guard = 'restcaja';

        // Cajero del Restaurante
        $cajero = Role::firstOrCreate(
            ['name' => 'cajero_restaurante', 'guard_name' => $guard],
            ['name' => 'cajero_restaurante', 'guard_name' => $guard]
        );
        $cajero->syncPermissions([
            'order.list', 'order.create', 'order.edit', 'order.view',
        ]);
        $this->command->info('  ✓ Rol cajero_restaurante creado');

        // Supervisor de Caja
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor_caja_restaurante', 'guard_name' => $guard],
            ['name' => 'supervisor_caja_restaurante', 'guard_name' => $guard]
        );
        $supervisor->syncPermissions([
            'order.list', 'order.create', 'order.edit', 'order.delete', 'order.view',
        ]);
        $this->command->info('  ✓ Rol supervisor_caja_restaurante creado');
    }

    private function createKioskInventarioRoles(): void
    {
        $guard = 'kioskinvetario';

        // Administrador de Inventario Kiosko
        $admin = Role::firstOrCreate(
            ['name' => 'kiosk_inventario_admin', 'guard_name' => $guard],
            ['name' => 'kiosk_inventario_admin', 'guard_name' => $guard]
        );
        $admin->syncPermissions([
            'kiosk_categories.list', 'kiosk_categories.create', 'kiosk_categories.edit', 'kiosk_categories.delete', 'kiosk_categories.view',
            'kiosk_products.list', 'kiosk_products.create', 'kiosk_products.edit', 'kiosk_products.delete', 'kiosk_products.view',
        ]);
        $this->command->info('  ✓ Rol kiosk_inventario_admin creado');

        // Operador de Inventario Kiosko
        $operador = Role::firstOrCreate(
            ['name' => 'kiosk_inventario_operador', 'guard_name' => $guard],
            ['name' => 'kiosk_inventario_operador', 'guard_name' => $guard]
        );
        $operador->syncPermissions([
            'kiosk_categories.list', 'kiosk_categories.view',
            'kiosk_products.list', 'kiosk_products.create', 'kiosk_products.edit', 'kiosk_products.view',
        ]);
        $this->command->info('  ✓ Rol kiosk_inventario_operador creado');
    }

    private function createKioskCajaRoles(): void
    {
        $guard = 'kioskcaja';

        // Cajero del Kiosko
        $cajero = Role::firstOrCreate(
            ['name' => 'cajero_kiosko', 'guard_name' => $guard],
            ['name' => 'cajero_kiosko', 'guard_name' => $guard]
        );
        $cajero->syncPermissions([
            'caja.list', 'caja.create', 'caja.view',
            'payment_type.list', 'payment_type.view',
            'tax.list', 'tax.view',
        ]);
        $this->command->info('  ✓ Rol cajero_kiosko creado');

        // Supervisor de Caja Kiosko
        $supervisor = Role::firstOrCreate(
            ['name' => 'supervisor_caja_kiosko', 'guard_name' => $guard],
            ['name' => 'supervisor_caja_kiosko', 'guard_name' => $guard]
        );
        $supervisor->syncPermissions([
            'caja.list', 'caja.create', 'caja.edit', 'caja.view', 'caja.close', 'caja.report',
            'payment_type.list', 'payment_type.create', 'payment_type.edit', 'payment_type.view',
            'tax.list', 'tax.create', 'tax.edit', 'tax.view',
            'compras.list', 'compras.create', 'compras.edit', 'compras.view',
        ]);
        $this->command->info('  ✓ Rol supervisor_caja_kiosko creado');

        // Administrador de Caja Kiosko
        $admin = Role::firstOrCreate(
            ['name' => 'admin_caja_kiosko', 'guard_name' => $guard],
            ['name' => 'admin_caja_kiosko', 'guard_name' => $guard]
        );
        $admin->syncPermissions([
            'caja.list', 'caja.create', 'caja.edit', 'caja.delete', 'caja.view', 'caja.close', 'caja.report',
            'payment_type.list', 'payment_type.create', 'payment_type.edit', 'payment_type.delete', 'payment_type.view',
            'tax.list', 'tax.create', 'tax.edit', 'tax.delete', 'tax.view',
            'compras.list', 'compras.create', 'compras.edit', 'compras.delete', 'compras.view',
        ]);
        $this->command->info('  ✓ Rol admin_caja_kiosko creado');
    }

    private function createClientesRoles(): void
    {
        $guard = 'clientes';

        // Administrador de Clientes
        $admin = Role::firstOrCreate(
            ['name' => 'clientes_admin', 'guard_name' => $guard],
            ['name' => 'clientes_admin', 'guard_name' => $guard]
        );
        $admin->syncPermissions([
            'clientes.list', 'clientes.create', 'clientes.edit', 'clientes.delete', 'clientes.view',
        ]);
        $this->command->info('  ✓ Rol clientes_admin creado');

        // Operador de Clientes
        $operador = Role::firstOrCreate(
            ['name' => 'clientes_operador', 'guard_name' => $guard],
            ['name' => 'clientes_operador', 'guard_name' => $guard]
        );
        $operador->syncPermissions([
            'clientes.list', 'clientes.create', 'clientes.edit', 'clientes.view',
        ]);
        $this->command->info('  ✓ Rol clientes_operador creado');
    }
}

