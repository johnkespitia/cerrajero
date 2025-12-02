<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RestCocinaPermissionsSeeder extends Seeder
{
    /**
     * Guard name para el módulo de cocina
     */
    private const GUARD = 'restcocina';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando permisos del módulo RestCocina...');

        $permissions = [
            'recipes.list' => 'Listar recetas',
            'recipes.create' => 'Crear recetas',
            'recipes.edit' => 'Editar recetas',
            'recipes.delete' => 'Eliminar recetas',
            'recipes.view' => 'Ver detalles de receta',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command->info('Permisos de RestCocina creados exitosamente.');
    }
}

