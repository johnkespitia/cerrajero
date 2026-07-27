<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetCoreDatabaseCommand extends Command
{
    protected $signature = 'db:reset-core
                            {--force : Ejecutar sin confirmación}';

    protected $description = 'Recrea la base de datos y deja solo admin, permisos, roles y tablas maestras';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Esto borrará TODOS los datos. ¿Continuar?', false)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $this->warn('Recreando esquema y aplicando bootstrap core...');

        $this->call('migrate:fresh', [
            '--force' => true,
            '--seed' => true,
            '--seeder' => 'Database\\Seeders\\CoreBootstrapSeeder',
        ]);

        $this->info('Base de datos reseteada. Solo quedan admin, permisos, roles y tablas maestras.');

        return self::SUCCESS;
    }
}
