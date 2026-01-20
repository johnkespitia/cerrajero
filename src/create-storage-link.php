<?php
/**
 * Script temporal para crear el symlink de storage en GoDaddy
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a la raíz de tu aplicación Laravel en el servidor
 * 2. Accede vía navegador: https://tu-dominio.com/create-storage-link.php
 * 3. O ejecuta por SSH: php create-storage-link.php
 * 4. ELIMINA ESTE ARCHIVO después de usarlo por seguridad
 * 
 * IMPORTANTE: Este script funciona desde el navegador si el servidor
 * permite crear symlinks. Algunos hostings pueden bloquearlo por seguridad.
 */

// Configurar para mostrar errores (útil para debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ruta base de la aplicación (ajusta si es necesario)
$basePath = __DIR__;

// Rutas
$target = $basePath . '/storage/app/public';
$link = $basePath . '/public/storage';

echo "=== Crear Symlink de Storage ===\n\n";
echo "Target: {$target}\n";
echo "Link: {$link}\n\n";

// Verificar que el directorio target existe
if (!file_exists($target)) {
    echo "❌ ERROR: El directorio target no existe: {$target}\n";
    echo "   Asegúrate de que el archivo logocv.png esté en: storage/app/public/\n";
    exit(1);
}

// Verificar si el link ya existe
if (file_exists($link)) {
    if (is_link($link)) {
        $actualTarget = readlink($link);
        if ($actualTarget === $target || $actualTarget === '../storage/app/public') {
            echo "✅ El symlink ya existe y apunta correctamente\n";
            echo "   Link actual: {$link} -> {$actualTarget}\n";
            exit(0);
        } else {
            echo "⚠️  Ya existe un symlink pero apunta a otro lugar\n";
            echo "   Eliminando symlink existente...\n";
            unlink($link);
        }
    } else {
        echo "⚠️  Ya existe un directorio 'storage' en public (no es un symlink)\n";
        echo "   Por favor elimínalo manualmente antes de continuar\n";
        exit(1);
    }
}

// Crear el symlink
echo "Creando symlink...\n";
echo "Verificando permisos...\n";

// Verificar permisos del directorio public
if (!is_writable($basePath . '/public')) {
    echo "⚠️  ADVERTENCIA: El directorio public no tiene permisos de escritura\n";
    echo "   Intenta cambiar permisos a 755 o 775\n";
}

// Intentar con ruta relativa primero (más compatible)
$relativeTarget = '../storage/app/public';
echo "Intentando crear symlink con ruta relativa: {$relativeTarget}\n";

if (@symlink($relativeTarget, $link)) {
    // Verificar que se creó correctamente
    if (is_link($link)) {
        $actualTarget = readlink($link);
        echo "✅ Symlink creado exitosamente usando ruta relativa\n";
        echo "   {$link} -> {$actualTarget}\n";
        echo "\n";
        echo "🎉 ¡ÉXITO! El symlink se creó correctamente.\n";
        echo "   Ahora puedes acceder al logo en:\n";
        echo "   " . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . "/storage/logocv.png\n";
        exit(0);
    }
}

// Si falla, intentar con ruta absoluta
echo "La ruta relativa falló. Intentando con ruta absoluta...\n";
if (@symlink($target, $link)) {
    // Verificar que se creó correctamente
    if (is_link($link)) {
        $actualTarget = readlink($link);
        echo "✅ Symlink creado exitosamente usando ruta absoluta\n";
        echo "   {$link} -> {$actualTarget}\n";
        echo "\n";
        echo "🎉 ¡ÉXITO! El symlink se creó correctamente.\n";
        echo "   Ahora puedes acceder al logo en:\n";
        echo "   " . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . "/storage/logocv.png\n";
        exit(0);
    }
}

// Si ambos fallan, mostrar error detallado
echo "❌ ERROR: No se pudo crear el symlink\n\n";
echo "Posibles causas:\n";
echo "   1. Permisos insuficientes en el directorio public/\n";
echo "   2. El servidor (GoDaddy) bloquea la creación de symlinks por seguridad\n";
echo "   3. Ya existe un archivo/directorio llamado 'storage' en public/\n";
echo "   4. La función symlink() está deshabilitada en PHP\n\n";

// Verificar si symlink está habilitado
if (!function_exists('symlink')) {
    echo "⚠️  La función symlink() NO está disponible en este servidor\n";
    echo "   Esto significa que el hosting no permite crear symlinks\n\n";
}

// Verificar permisos
$publicDir = $basePath . '/public';
echo "Información de permisos:\n";
echo "   Directorio public: " . (is_writable($publicDir) ? "✅ Escribible" : "❌ No escribible") . "\n";
echo "   Permisos: " . substr(sprintf('%o', fileperms($publicDir)), -4) . "\n\n";

echo "SOLUCIONES ALTERNATIVAS:\n\n";
echo "Opción 1: Copiar el logo directamente a public/\n";
echo "   1. En cPanel File Manager, copia storage/app/public/logocv.png\n";
echo "   2. Pégalo en public/logocv.png\n";
echo "   3. El código ya buscará ahí automáticamente\n\n";

echo "Opción 2: Contactar soporte de GoDaddy\n";
echo "   Pide que habiliten la creación de symlinks o que creen el symlink por ti\n\n";

echo "Opción 3: Usar un hosting que permita SSH\n";
echo "   Con SSH puedes ejecutar: php artisan storage:link\n";
exit(1);
