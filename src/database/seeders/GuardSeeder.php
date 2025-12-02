<?php

namespace Database\Seeders;

use App\Models\Guard;
use Illuminate\Database\Seeder;

class GuardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Creando guards del sistema...');

        $guards = [
            [
                'name' => 'cerrajero',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'usuarios',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'restbodega',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'restcocina',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'restcaja',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'kioskinvetario',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'kioskcaja',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'clientes',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
            [
                'name' => 'reservas',
                'driver' => 'sanctum',
                'provider' => 'users',
            ],
        ];

        foreach ($guards as $guardData) {
            Guard::firstOrCreate(
                ['name' => $guardData['name']],
                $guardData
            );
            $this->command->info("  ✓ Guard '{$guardData['name']}' creado");
        }

        $this->command->info('Guards creados exitosamente.');
    }
}

