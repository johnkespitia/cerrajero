<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Bootstrap mínimo: guards, permisos, roles, usuario admin y tablas maestras.
 * No incluye datos de demo (habitaciones, reservas, usuarios por rol, etc.).
 */
class CoreBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando bootstrap core del sistema...');

        $this->command->info('Creando guards...');
        $this->call(GuardSeeder::class);

        $this->command->info('Creando permisos...');
        $this->call(PermissionsSeeder::class);
        $this->call(RestBodegaPermissionsSeeder::class);
        $this->call(RestCocinaPermissionsSeeder::class);
        $this->call(RestCajaPermissionsSeeder::class);
        $this->call(KioskInventarioPermissionsSeeder::class);
        $this->call(KioskCajaPermissionsSeeder::class);
        $this->call(ClientesPermissionsSeeder::class);
        $this->call(UsuariosPermissionsSeeder::class);
        $this->call(ReservationPermissionsSeeder::class);
        $this->call(CleaningMaintenancePermissionsSeeder::class);
        $this->call(MinibarPermissionsSeeder::class);
        $this->call(RoomInventoryPermissionsSeeder::class);
        $this->call(ElectronicInvoicingPermissionsSeeder::class);

        $this->command->info('Creando roles...');
        $this->call(RolSeeder::class);
        $this->call(ModuleRolesSeeder::class);
        $this->call(ReservationRolesSeeder::class);

        $this->command->info('Creando usuario administrador...');
        $this->call(UserSeeder::class);

        $this->command->info('Creando tablas maestras...');
        $this->call(PaymentTypeSeeder::class);

        $this->command->info('Bootstrap core completado.');
    }
}
