# Diseño del Template Base de Emails

## Características del Diseño

### 1. **Header (Encabezado)**
- Fondo verde degradado (#2d5016 a #3a6b1f) - color corporativo de Campo Verde
- Logo del hotel (o texto "Campo Verde" si no hay logo)
- Subtítulo "Centro Vacacional"
- Diseño centrado y profesional

### 2. **Contenido Principal**
- Fondo blanco limpio
- Padding generoso para legibilidad
- Tipografía clara y legible (Segoe UI, Arial)
- Espaciado adecuado entre elementos

### 3. **Cajas de Información**
- **info-box**: Caja estándar con borde izquierdo verde
- **success-box**: Verde claro para mensajes de éxito
- **warning-box**: Amarillo para advertencias
- **error-box**: Rojo para errores/cancelaciones
- **payment-box**: Verde claro para información de pagos
- **summary-box**: Naranja claro para resúmenes financieros

### 4. **Tablas**
- Diseño limpio y profesional
- Encabezados con fondo gris claro
- Filas alternadas para mejor legibilidad
- Alineación correcta para números (text-right)

### 5. **Footer (Pie de Página)**
- Fondo verde oscuro (#2d5016) - consistente con el header
- Información de contacto del hotel:
  - 📍 Dirección: Campo Verde - Cocorná, Antioquia
  - 📧 Email: c.vacacionalcampoverde@gmail.com
  - 📞 Teléfono: 322 614 3787
- Divider visual entre secciones
- Disclaimer sobre correo automático
- Copyright

### 6. **Responsive Design**
- Adaptado para dispositivos móviles
- Ancho máximo de 600px
- Padding ajustado en pantallas pequeñas
- Tablas con fuente más pequeña en móviles

## Colores Corporativos

- **Verde Principal**: #2d5016
- **Verde Secundario**: #3a6b1f
- **Verde Claro (éxito)**: #4caf50
- **Amarillo (advertencia)**: #ffc107
- **Rojo (error)**: #dc3545
- **Naranja (resumen)**: #ff9800
- **Azul (info)**: #0066cc

## Ejemplos de Uso

### Email Simple (Recordatorio)
- Header con logo
- Contenido con cajas de información
- Footer con datos del hotel

### Email Complejo (Confirmación de Pago)
- Header con logo
- Múltiples cajas de información
- Tablas con datos detallados
- Footer con datos del hotel

## Ventajas del Diseño

1. **Consistencia**: Todos los emails tendrán el mismo look & feel
2. **Profesionalismo**: Diseño moderno y limpio
3. **Branding**: Logo e información del hotel siempre visibles
4. **Legibilidad**: Tipografía y espaciado optimizados
5. **Responsive**: Funciona bien en todos los dispositivos
6. **Mantenibilidad**: Un solo template base, fácil de actualizar

## Próximos Pasos

Una vez aprobado el diseño, se adaptarán todos los emails existentes:
- reservation_confirmation.blade.php
- checkout_confirmation.blade.php
- check_in_reminder.blade.php
- check_in_confirmation.blade.php
- check_out_reminder.blade.php
- cancellation_notification.blade.php
- reservation_update.blade.php
- kiosk_otp.blade.php
- payment_confirmation.blade.php
- inventory_limit.blade.php (si aplica)
