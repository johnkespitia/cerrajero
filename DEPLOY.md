# Despliegue API a producción

Push a la rama **`campo_verde_api`** (o ejecución manual del workflow) dispara `Deploy Backend via SSH` (rsync).

## Secrets requeridos en GitHub (`cerrajero`)

| Secret | Descripción |
|--------|-------------|
| `SSH_HOST` | Host o IP del servidor |
| `SSH_USER` | Usuario SSH (ej. `u123456789`) |
| `SSH_PRIVATE_KEY` | Clave privada completa (PEM/OpenSSH) |
| `SSH_PORT` | Puerto SSH (opcional, default `22`) |
| `SSH_BACKEND_DIR` | Ruta absoluta del API en el servidor |
| `APP_KEY`, `APP_URL`, `DB_*`, etc. | Variables de `.env` (como antes) |

## Preparación en el servidor (una vez)

1. Genera un par de claves dedicado al CI:

   ```bash
   ssh-keygen -t ed25519 -C "github-actions-campoverde" -f ~/.ssh/campoverde_deploy -N ""
   ```

2. Agrega la clave **pública** en el hosting:

   ```bash
   cat ~/.ssh/campoverde_deploy.pub >> ~/.ssh/authorized_keys
   chmod 600 ~/.ssh/authorized_keys
   ```

3. Guarda la clave **privada** en GitHub → Settings → Secrets → `SSH_PRIVATE_KEY`.

4. Verifica acceso desde tu máquina:

   ```bash
   ssh -i ~/.ssh/campoverde_deploy USER@HOST "echo ok"
   ```

## Qué hace el workflow

1. Build de Laravel (`composer install --no-dev`)
2. `rsync` al directorio remoto (sin sobrescribir `.env` ni logs/cache)
3. Por SSH en el servidor:
   - `php update-env.php`
   - `php artisan migrate --force`
   - `php artisan storage:link`

## Migraciones pendientes en producción (si `migrate` falla)

Si la BD ya existía y `migrate` no puede correr todo, aplica al menos:

```sql
ALTER TABLE room_types
  ADD COLUMN image_url VARCHAR(500) NULL AFTER description,
  ADD COLUMN gallery JSON NULL AFTER image_url;
```

Luego marca la migración en Laravel:

```sql
INSERT INTO migrations (migration, batch)
SELECT '2026_07_08_120100_add_media_fields_to_room_types_table', COALESCE(MAX(batch), 0) + 1
FROM migrations
WHERE NOT EXISTS (
  SELECT 1 FROM migrations
  WHERE migration = '2026_07_08_120100_add_media_fields_to_room_types_table'
);
```

(Opcional) Tabla CMS del sitio:

```sql
-- Ver migración 2026_07_08_120000_create_site_settings_table.php
```

## Deploy manual local (opcional)

```bash
rsync -az --delete \
  --exclude '.env' \
  --exclude 'storage/logs/' \
  --exclude 'storage/framework/cache/' \
  --exclude 'storage/framework/sessions/' \
  --exclude 'storage/framework/views/' \
  -e "ssh -i ~/.ssh/campoverde_deploy" \
  ./deploy/ USER@HOST:/ruta/al/api/
```

## Relacionado

- Admin: repo `cerradura`, rama `campoverde`
- Sitio público: repo `campo_verde_ui`, rama `main`
