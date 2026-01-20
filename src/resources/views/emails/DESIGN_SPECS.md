# Design Specs - Template Base de Emails Campo Verde

## Paleta de Colores

### Base (dominante en el documento)
- **Fondo**: `#FFFFFF`
- **Texto principal** (títulos y números): `#111111`
- **Texto secundario** (párrafos / labels suaves): `#333333`
- **Líneas divisorias / bordes de tabla**: `#D9D9D9` (muy fino)

### Marca (del logo Campo Verde)
- **Verde marca**: `#2F6B3F`
  - Uso: acentos pequeños (títulos secundarios, bullets, enlaces)
- **Verde suave** (apoyo): `#6F9C7B`
  - Uso: hover, separadores suaves, chips
- **Dorado/mostaza del escudo**: `#C8A24A`
  - Uso: acento mínimo (íconos, highlight de totales)

### Recomendación práctica para email
Mantener el correo casi monocromático (negro/gris) y usar el verde solo en 1–2 lugares (links + mini títulos).

## Tipografía (email-safe)

### Stack recomendado
- **Heading (serif)**: `Georgia, "Times New Roman", Times, serif`
- **Body (sans)**: `Arial, Helvetica, sans-serif`

### Tamaños (desktop / mobile)
- **H1** (título principal, centrado): `24–28px / 22–24px`, `font-weight: 700`, `letter-spacing: 0.5px`
- **H2** (secciones): `14–16px`, `font-weight: 700`, en MAYÚSCULAS opcional
- **Body**: `13–14px`
- **Small / notas**: `12px`

### Estilo de números (totales)
`14–16px`, `font-weight: 700`, `color #111111`

## Espaciado y Layout

### Contenedor
- **Ancho**: `600px` (clásico email)
- **Padding general**: `24px`
- **Fondo externo**: `#F6F6F6` opcional (para "tarjeta" blanca central)

### Sistema de spacing
- `4px` (micro)
- `8px` (gap chico)
- `12px` (bloques cortos)
- `16px` (secciones)
- `24px` (separación grande)
- `32px` (respiro entre bloques principales)

### Divisores
- Línea horizontal `1px solid #D9D9D9`
- Margen vertical: `16–24px` arriba/abajo

## Componentes y Estructura

### A. Header
- **Izquierda**: logo (80–100px alto máx)
- **Centro** (o centrado debajo en mobile):
  - H1: "CONFIRMACIÓN PAGO"
  - Sub H1: "CAMPO VERDE"
- **Derecha**: "RESERVA #{{numero}}" en negrita

### B. Bloque "Cliente / Fechas"
Un bloque de datos en 2 columnas:
- **Columna izquierda**: labels (CLIENTE, CC, CEL, FECHA LLEGADA, FECHA SALIDA)
- **Columna derecha**: valores en negrita

### C. Tabla de ítems (Plan)
Tabla con header y líneas finas:
- **Columnas**: PLAN | noche | Cantidad Personas | Precio/u | Total
- **Filas**: ítems del plan
- **Alineación**: texto a la izquierda; números a la derecha

### D. Resumen de pagos (totales)
Bloque a la derecha (o 2 columnas):
- TOTAL RESERVA
- ABONÓ
- RESTA
Con valores alineados a la derecha y en negrita.

### E. Sección "Información de pago"
Bloque simple con:
- Nombre titular
- CC
- Banco / tipo
- Cuenta

### F. Sección "Horarios / Políticas"
Este bloque funciona muy bien como:
- **2 columnas en desktop**:
  - Izq: horarios (check-in/out, alimentación)
  - Der: recordatorios / políticas (bullets)
- **1 columna en mobile** (stack)

Bullets/íconos: En email, usar "•" o emojis (como el PDF) con moderación.

## Footer (estructura recomendada)

### Footer - bloque 1 (cierre humano)
- Texto grande: "¡Muchas gracias!"
- Línea divisoria fina

### Footer - bloque 2 (marca + contacto)
- Logo pequeño (40–56px) + "Campo Verde"
- Tel / WhatsApp
- Dirección (si aplica)
- Redes (links en verde marca)

### Footer - bloque 3 (legal)
- Texto 11–12px gris: políticas, condiciones, "Este correo es una confirmación automática…"

## Otros aspectos visuales importantes

- **Mucho blanco**: no llenar con fondos verdes; la estética es "documento limpio"
- **Marca como sello**: logo arriba y un "CAMPO VERDE" grande puede ir como texto de marca al final
- **Marca de agua**: el PDF sugiere un watermark grande (muy tenue). En email se puede simular con una imagen de fondo, pero muchos clientes de correo lo bloquean. Mejor: omitirlo o usarlo solo como imagen centrada muy tenue en un bloque (opcional)

## Design Tokens

```css
Container width: 600px
Radius: 0px (documento recto, sin tarjetas redondeadas)
Base font: Arial, Helvetica, sans-serif
Heading font: Georgia, "Times New Roman", Times, serif
Text: #111111, secondary #333333
Border: #D9D9D9
Brand green: #2F6B3F
Padding: 24px
Section gap: 24px
Divider: 1px solid #D9D9D9
```

## Responsive

- En mobile (< 600px):
  - Header se convierte en 3 filas (logo, título, reserva)
  - Bloques de 2 columnas se apilan verticalmente
  - Tablas con fuente más pequeña
  - Padding reducido a 16px
