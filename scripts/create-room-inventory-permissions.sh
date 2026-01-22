#!/bin/bash

# Script para crear permisos del módulo de Inventario de Habitaciones
# Este módulo se asocia al guard 'reservas' (módulo existente)

echo "=========================================="
echo "Creando permisos de Inventario de Habitaciones"
echo "Módulo: reservas (asociado al módulo existente)"
echo "=========================================="

# Ejecutar el seeder de permisos
docker exec cerrajero-web-server-1 php artisan db:seed --class=RoomInventoryPermissionsSeeder

echo ""
echo "=========================================="
echo "Permisos creados exitosamente"
echo "=========================================="
echo ""
echo "Los permisos están asociados al módulo 'reservas' (guard: reservas)"
echo ""
echo "Para verificar los permisos creados, ejecuta:"
echo "  docker exec cerrajero-web-server-1 php artisan tinker"
echo "  >>> Spatie\Permission\Models\Permission::where('guard_name', 'reservas')->where('name', 'like', 'room_inventory.%')->get(['name']);"
echo ""
