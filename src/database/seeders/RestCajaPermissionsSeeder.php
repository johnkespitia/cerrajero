<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RestCajaPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de caja del restaurante
     */
    private const GUARD = 'restcaja';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo RestCaja...');

        $permissions = [
            'order.list' => 'Listar órdenes',
            'order.create' => 'Crear órdenes',
            'order.edit' => 'Editar órdenes',
            'order.delete' => 'Eliminar órdenes',
            'order.view' => 'Ver detalles de orden',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('Permisos de RestCaja creados exitosamente.');
    }
}

