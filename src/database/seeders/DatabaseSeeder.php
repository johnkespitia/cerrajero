<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Iniciando seeders del sistema...');
        
        // ============================================
        // 1. Crear guards primero
        // ============================================
        $this->command->info('Creando guards...');
        $this->call(GuardSeeder::class);
        
        // ============================================
        // 2. Crear permisos de todos los módulos
        // ============================================
        $this->command->info('Creando permisos...');
        $this->call(PermissionsSeeder::class); // Módulo cerrajero (usuarios, roles, permisos, guards)
        $this->call(RestBodegaPermissionsSeeder::class);
        $this->call(RestCocinaPermissionsSeeder::class);
        $this->call(RestCajaPermissionsSeeder::class);
        $this->call(KioskInventarioPermissionsSeeder::class);
        $this->call(KioskCajaPermissionsSeeder::class);
        $this->call(ClientesPermissionsSeeder::class);
        $this->call(UsuariosPermissionsSeeder::class);
        $this->call(ReservationPermissionsSeeder::class);
        
        // ============================================
        // 3. Crear roles (depende de permisos)
        // ============================================
        $this->command->info('Creando roles...');
        $this->call(RolSeeder::class);
        $this->call(ModuleRolesSeeder::class);
        $this->call(ReservationRolesSeeder::class);
        
        // ============================================
        // 4. Crear usuarios (depende de roles)
        // ============================================
        $this->command->info('Creando usuarios...');
        $this->call(UserSeeder::class);
        $this->call(UsersByRoleSeeder::class);
        
        $this->command->info('✅ Todos los seeders completados exitosamente.');
    }
}
