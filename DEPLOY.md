# Despliegue API a producción

Push a la rama **`campo_verde_api`** (o ejecución manual del workflow) dispara `Deploy Backend to FTP`.

## Tras cada deploy

1. **Migraciones** (obligatorio si hay cambios de BD):

   Opción A — automática en CI (recomendada):

   Configura en GitHub Secrets del repo `cerrajero`:

   - `DEPLOY_MIGRATE_TOKEN` — token largo y secreto
   - `APP_URL` — URL base del API en producción (ej. `https://centrovacacionalcampoverde.com`)

   El workflow ejecutará:

   ```
   GET {APP_URL}/api/public/deploy/migrate?token={DEPLOY_MIGRATE_TOKEN}
   ```

   Opción B — terminal/cPanel en el servidor:

   ```bash
   php artisan migrate --force
   php artisan storage:link
   ```

   Opción C — script web (una vez, luego borrar):

   En `.env` de producción agrega:

   ```env
   DEPLOY_MIGRATE_TOKEN=un-token-secreto-largo
   ```

   Visita:

   ```
   https://TU-DOMINIO/api/public/deploy/migrate?token=un-token-secreto-largo
   ```

2. **Contenido inicial del sitio** (opcional, primera vez):

   ```bash
   php artisan db:seed --class=HospitalityDemoSeeder --force
   ```

## Secrets GitHub

`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_BACKEND_DIR`, `APP_URL`, `DEPLOY_MIGRATE_TOKEN` y variables de `.env` vía secrets del workflow.

## Relacionado

- Admin: repo `cerradura`, rama `campoverde`
- Sitio público: repo `campo_verde_ui`, rama `main`
