<?php

/**
 * Script de prueba para verificar la configuración de correo
 * 
 * Uso: php test_email.php tu-email@ejemplo.com
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$testEmail = $argv[1] ?? 'test@example.com';

echo "=== Prueba de Configuración de Correo ===\n\n";

// Mostrar configuración actual
echo "Configuración SMTP:\n";
echo "  Host: " . config('mail.mailers.smtp.host') . "\n";
echo "  Port: " . config('mail.mailers.smtp.port') . "\n";
echo "  Encryption: " . config('mail.mailers.smtp.encryption') . "\n";
echo "  Username: " . config('mail.mailers.smtp.username') . "\n";
echo "  From Address: " . config('mail.from.address') . "\n";
echo "  From Name: " . config('mail.from.name') . "\n\n";

try {
    echo "Intentando enviar correo de prueba a: {$testEmail}\n";
    
    \Illuminate\Support\Facades\Mail::raw('Este es un correo de prueba desde Campo Verde.', function ($message) use ($testEmail) {
        $message->to($testEmail)
                ->subject('Prueba de Correo - Campo Verde');
    });
    
    echo "✓ Correo enviado exitosamente!\n";
    echo "Revisa tu bandeja de entrada (y spam) en: {$testEmail}\n";
    
} catch (\Swift_TransportException $e) {
    echo "✗ Error de transporte SMTP:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nPosibles causas:\n";
    echo "  - Credenciales incorrectas\n";
    echo "  - Puerto o encriptación incorrectos\n";
    echo "  - Servidor SMTP no accesible\n";
    echo "  - Firewall bloqueando la conexión\n";
} catch (\Exception $e) {
    echo "✗ Error:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "  Tipo: " . get_class($e) . "\n";
}

echo "\n=== Fin de la prueba ===\n";



