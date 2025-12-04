# Configuración de Correo Electrónico

## Configuración SSL/TLS (Recomendada)

Esta es la configuración recomendada para el servidor de correo de Campo Verde.

### Variables de entorno (.env)

Agrega las siguientes variables a tu archivo `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=centrovacacionalcampoverde.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@centrovacacionalcampoverde.com
MAIL_PASSWORD=tu_contraseña_aqui
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@centrovacacionalcampoverde.com
MAIL_FROM_NAME="Centro Vacacional Campo Verde"
```

### Detalles de configuración:

- **Servidor SMTP:** `centrovacacionalcampoverde.com`
- **Puerto SMTP:** `465` (SSL/TLS)
- **Usuario:** `no-reply@centrovacacionalcampoverde.com`
- **Contraseña:** La contraseña de la cuenta de correo electrónico
- **Encriptación:** `ssl` (para puerto 465)
- **Autenticación:** Requerida (IMAP, POP3 y SMTP)

## Configuración Alternativa (No SSL - No Recomendada)

Si necesitas usar la configuración sin SSL (no recomendada), usa:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.centrovacacionalcampoverde.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@centrovacacionalcampoverde.com
MAIL_PASSWORD=tu_contraseña_aqui
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@centrovacacionalcampoverde.com
MAIL_FROM_NAME="Centro Vacacional Campo Verde"
```

### Detalles de configuración alternativa:

- **Servidor SMTP:** `mail.centrovacacionalcampoverde.com`
- **Puerto SMTP:** `587` (TLS)
- **Usuario:** `no-reply@centrovacacionalcampoverde.com`
- **Contraseña:** La contraseña de la cuenta de correo electrónico
- **Encriptación:** `tls` (para puerto 587)

## Notas Importantes

1. **Seguridad:** Se recomienda usar la configuración SSL/TLS (puerto 465) en lugar de la configuración sin SSL.

2. **Contraseña:** Asegúrate de usar la contraseña correcta de la cuenta de correo `no-reply@centrovacacionalcampoverde.com`.

3. **Pruebas:** Después de configurar, puedes probar el envío de correos usando:
   ```bash
   php artisan tinker
   Mail::raw('Test email', function ($message) {
       $message->to('tu-email@ejemplo.com')
                ->subject('Test Email');
   });
   ```

4. **Logs:** Si hay problemas, revisa los logs en `storage/logs/laravel.log`.



