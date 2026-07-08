<?php

/**
 * Ejecuta migraciones pendientes en producción (una sola vez tras deploy).
 *
 * Uso:
 *   https://tu-dominio-api/run-migrations.php?token=EL_TOKEN_DE_PRODUCCION
 *
 * Preferir la ruta API (accesible en este hosting):
 *   https://tu-dominio/api/public/deploy/migrate?token=EL_TOKEN_DE_PRODUCCION
 *
 * Configura en .env de producción:
 *   DEPLOY_MIGRATE_TOKEN=un-token-largo-y-secreto
 *
 * Elimina o desactiva este archivo después de usarlo.
 */

declare(strict_types=1);

function readDeployMigrateToken(string $envPath): string
{
    if (! is_readable($envPath)) {
        return '';
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        if (trim($key) !== 'DEPLOY_MIGRATE_TOKEN') {
            continue;
        }

        return trim($value, " \t\"'");
    }

    return '';
}

$token = $_GET['token'] ?? '';
$expected = getenv('DEPLOY_MIGRATE_TOKEN') ?: readDeployMigrateToken(__DIR__ . '/../.env');

if ($expected === '' || ! hash_equals($expected, (string) $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
Illuminate\Support\Facades\Artisan::call('storage:link');

header('Content-Type: text/plain; charset=utf-8');
echo "Migrations:\n";
echo Illuminate\Support\Facades\Artisan::output();
echo "\nStorage link ensured.\n";
