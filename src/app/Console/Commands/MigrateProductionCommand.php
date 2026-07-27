<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MigrateProductionCommand extends Command
{
    protected $signature = 'migrate:production {--force : Run without confirmation in production}';

    protected $description = 'Run pending migrations, marking already-applied schema as migrated when safe';

    public function handle(): int
    {
        if (! $this->option('force') && $this->laravel->environment('production')) {
            $this->error('Use --force to run migrations in production.');

            return self::FAILURE;
        }

        $migrator = $this->laravel['migrator'];
        $repository = $migrator->getRepository();
        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $repository->getRan();
        $pending = array_diff(array_keys($files), $ran);

        if ($pending === []) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        sort($pending);
        $batch = (int) DB::table('migrations')->max('batch') + 1;
        $applied = 0;
        $skipped = 0;

        foreach ($pending as $migration) {
            $path = $files[$migration];

            try {
                $migrator->run([$path]);
                $applied++;
                $this->line("Migrated: {$migration}");
            } catch (QueryException $exception) {
                if (! $this->isAlreadyAppliedSchemaConflict($exception)) {
                    $this->error("Failed on {$migration}: {$exception->getMessage()}");

                    return self::FAILURE;
                }

                if (! in_array($migration, $repository->getRan(), true)) {
                    DB::table('migrations')->insert([
                        'migration' => $migration,
                        'batch' => $batch,
                    ]);
                }

                $skipped++;
                $this->warn("Skipped (schema already present): {$migration}");
            }
        }

        $this->info("Done. Applied: {$applied}, skipped as already present: {$skipped}.");

        return self::SUCCESS;
    }

    protected function isAlreadyAppliedSchemaConflict(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = $exception->getMessage();

        if (in_array($sqlState, ['42S01', '42S21'], true)) {
            return true;
        }

        return (bool) preg_match(
            '/(already exists|Duplicate column|Duplicate key name|Can\'t DROP|check that it exists)/i',
            $message
        );
    }
}
