# Despliegue API a producción

Push a la rama **`campo_verde_api`** dispara el workflow `Deploy Backend to FTP`.

## Tras cada deploy

1. **Migraciones** (obligatorio si hay cambios de BD):

   Opción A — terminal/cPanel en el servidor:

   ```bash
   php artisan migrate --force
   php artisan storage:link
   ```

   Opción B — script web (una vez, luego borrar):

   En `.env` de producción agrega:

   ```env
   DEPLOY_MIGRATE_TOKEN=un-token-secreto-largo
   ```

   Visita:

   ```
   https://TU-DOMINIO-API/run-migrations.php?token=un-token-secreto-largo
   ```

2. **Contenido inicial del sitio** (opcional, primera vez):

   ```bash
   php artisan db:seed --class=HospitalityDemoSeeder --force
   ```

## Secrets GitHub

`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_BACKEND_DIR` y variables de `.env` vía secrets del workflow.
