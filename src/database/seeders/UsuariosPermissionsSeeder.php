<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class UsuariosPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de usuarios (formas de pago)
     */
    private const GUARD = 'usuarios';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo Usuarios (formas de pago)...');

        $permissions = [
            'paymenttypes.list' => 'Listar formas de pago',
            'paymenttypes.create' => 'Crear formas de pago',
            'paymenttypes.edit' => 'Editar formas de pago',
            'paymenttypes.delete' => 'Eliminar formas de pago',
            'paymenttypes.view' => 'Ver detalles de forma de pago',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('Permisos de Usuarios (formas de pago) creados exitosamente.');
    }
}

