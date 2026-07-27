<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ElectronicInvoicingPermissionsSeeder extends Seeder
{
    private const GUARD = 'cerrajero';

    public function run(): void
    {
        $this->command->info('Creando permisos del módulo Facturación Electrónica...');

        $permissions = [
            'electronic_invoicing.admin' => 'Administrar configuración fiscal y credenciales DIAN',
            'electronic_invoicing.list' => 'Consultar documentos electrónicos',
            'electronic_invoicing.create' => 'Emitir documentos electrónicos',
            'electronic_invoicing.radian' => 'Registrar eventos RADIAN',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('Permisos de Facturación Electrónica creados exitosamente.');
    }
}
