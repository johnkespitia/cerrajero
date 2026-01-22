#!/bin/bash

# Script para configurar el módulo de minibar
# Ejecutar desde el directorio cerrajero/

echo "🚀 Configurando módulo de Minibar..."
echo ""

# Verificar que estamos en el directorio correcto
if [ ! -f "src/artisan" ]; then
    echo "❌ Error: Este script debe ejecutarse desde el directorio cerrajero/"
    exit 1
fi

# Verificar si Docker está corriendo
if ! docker compose ps | grep -q "web-server"; then
    echo "⚠️  Los contenedores de Docker no están corriendo."
    echo "   Ejecuta: docker compose up -d"
    exit 1
fi

echo "📦 Ejecutando migraciones..."
docker compose exec -T web-server php artisan migrate --force

if [ $? -eq 0 ]; then
    echo "✅ Migraciones ejecutadas exitosamente"
else
    echo "❌ Error ejecutando migraciones"
    exit 1
fi

echo ""
echo "🔐 Creando permisos del módulo Minibar..."
docker compose exec -T web-server php artisan db:seed --class=MinibarPermissionsSeeder

if [ $? -eq 0 ]; then
    echo "✅ Permisos creados exitosamente"
else
    echo "⚠️  Los permisos pueden ya existir o hubo un error"
fi

echo ""
echo "✅ Configuración del módulo Minibar completada!"
echo ""
echo "📝 Próximos pasos:"
echo "   1. Asignar permisos a los roles correspondientes"
echo "   2. Crear categorías y productos del minibar"
echo "   3. Configurar stock por habitación"
echo ""
