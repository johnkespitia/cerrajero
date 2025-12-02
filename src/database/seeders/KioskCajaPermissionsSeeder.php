<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class KioskCajaPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de caja del kiosko
     */
    private const GUARD = 'kioskcaja';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo KioskCaja...');

        // Caja general
        $this->createCajaPermissions();

        // Formas de pago
        $this->createPaymentTypePermissions();

        // Impuestos
        $this->createTaxPermissions();

        // Compras
        $this->createComprasPermissions();

        $this->command->info('Permisos de KioskCaja creados exitosamente.');
    }

    private function createCajaPermissions(): void
    {
        $permissions = [
            'caja.list' => 'Listar caja y facturas',
            'caja.create' => 'Crear facturas',
            'caja.edit' => 'Editar facturas',
            'caja.delete' => 'Eliminar facturas',
            'caja.view' => 'Ver detalles de factura',
            'caja.close' => 'Cerrar caja',
            'caja.report' => 'Ver reportes de caja',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de caja creados');
    }

    private function createPaymentTypePermissions(): void
    {
        $permissions = [
            'payment_type.list' => 'Listar formas de pago',
            'payment_type.create' => 'Crear formas de pago',
            'payment_type.edit' => 'Editar formas de pago',
            'payment_type.delete' => 'Eliminar formas de pago',
            'payment_type.view' => 'Ver detalles de forma de pago',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de formas de pago creados');
    }

    private function createTaxPermissions(): void
    {
        $permissions = [
            'tax.list' => 'Listar impuestos',
            'tax.create' => 'Crear impuestos',
            'tax.edit' => 'Editar impuestos',
            'tax.delete' => 'Eliminar impuestos',
            'tax.view' => 'Ver detalles de impuesto',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de impuestos creados');
    }

    private function createComprasPermissions(): void
    {
        $permissions = [
            'compras.list' => 'Listar compras',
            'compras.create' => 'Crear compras',
            'compras.edit' => 'Editar compras',
            'compras.delete' => 'Eliminar compras',
            'compras.view' => 'Ver detalles de compra',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('  ✓ Permisos de compras creados');
    }
}

