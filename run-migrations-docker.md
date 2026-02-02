# Ejecutar migraciones con Docker (cerrajero)

Desde la raíz del proyecto, en la carpeta donde está `docker-compose.yaml`:

```bash
cd cerrajero
docker compose exec web-server php artisan migrate --force
```

Si usas `docker-compose` (con guión):

```bash
cd cerrajero
docker-compose exec web-server php artisan migrate --force
```

Eso ejecuta las migraciones dentro del contenedor `web-server` (donde está PHP y Laravel) y crea/actualiza las tablas en la base MySQL del contenedor `mysql-server`.

Después de esto, la columna `purchase_price` en `minibar_products` y la tabla `minibar_warehouse_stock` quedarán creadas.
