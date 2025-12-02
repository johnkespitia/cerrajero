<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ClientesPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de clientes
     */
    private const GUARD = 'clientes';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo Clientes...');

        $permissions = [
            'clientes.list' => 'Listar clientes',
            'clientes.create' => 'Crear clientes',
            'clientes.edit' => 'Editar clientes',
            'clientes.delete' => 'Eliminar clientes',
            'clientes.view' => 'Ver detalles de cliente',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('Permisos de Clientes creados exitosamente.');
    }
}

