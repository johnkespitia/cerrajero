# Extensiones PHP Requeridas - Campo Verde API

## 🔴 CRÍTICAS (Obligatorias - Sin estas la aplicación NO funcionará)

Estas extensiones son **absolutamente necesarias** para que Laravel funcione:

- ✅ **pdo** - PHP Data Objects (base para conexiones de base de datos)
- ✅ **pdo_mysql** - Driver PDO para MySQL (conexión a base de datos)
- ✅ **mbstring** - Manipulación de cadenas multibyte (requerido por Laravel)
- ✅ **xml** - Procesamiento XML (requerido por Laravel)
- ✅ **openssl** - Funciones criptográficas (requerido por Laravel)
- ✅ **json** - Codificación/decodificación JSON (requerido por Laravel)
- ✅ **curl** - Cliente HTTP (requerido por Laravel y Guzzle)
- ✅ **fileinfo** - Detección de tipo de archivo (requerido por Laravel)
- ✅ **zip** - Compresión ZIP (requerido por Composer y Laravel)

## 🟡 IMPORTANTES (Recomendadas - Funcionalidades específicas)

Estas extensiones son necesarias para funcionalidades específicas de tu aplicación:

- ✅ **bcmath** - Cálculos matemáticos de precisión arbitraria (usado en cálculos financieros)
- ✅ **gd** - Manipulación de imágenes (usado por DomPDF y posiblemente para imágenes)
- ✅ **dom** - DOM XML (usado por DomPDF)
- ✅ **simplexml** - SimpleXML (usado por Laravel y paquetes)
- ✅ **tokenizer** - Tokenizador PHP (requerido por Laravel)
- ✅ **ctype** - Funciones de verificación de caracteres (requerido por Laravel)

## 🟢 OPCIONALES (Mejoran el rendimiento o funcionalidades adicionales)

Estas extensiones mejoran el rendimiento pero no son estrictamente necesarias:

- ⚪ **opcache** - Cache de opcodes (mejora significativa de rendimiento)
- ⚪ **exif** - Metadatos de imágenes (si trabajas con imágenes)
- ⚪ **intl** - Internacionalización (si necesitas múltiples idiomas)
- ⚪ **soap** - Protocolo SOAP (si necesitas servicios SOAP)

## 📋 Lista Completa para cPanel

### Extensiones que DEBES marcar (en orden alfabético):

```
✅ bcmath
✅ curl
✅ dom
✅ fileinfo
✅ gd
✅ json
✅ mbstring
✅ openssl
✅ pdo
✅ pdo_mysql
✅ simplexml
✅ tokenizer
✅ xml
✅ zip
```

### Extensiones que ya deberían estar marcadas (core de PHP):

```
✅ ctype
✅ date
✅ filter
✅ hash
✅ iconv
✅ libxml
✅ session
✅ spl
✅ standard
```

## 🔍 Verificación

Después de habilitar las extensiones, puedes verificar que todo esté correcto accediendo a:

```
https://tu-dominio.com/check_pdo.php
```

O ejecutando en SSH:

```bash
php -m | grep -E "pdo|mbstring|xml|openssl|json|curl|fileinfo|zip|bcmath|gd"
```

Deberías ver todas estas extensiones listadas.

## ⚠️ Nota Importante

En la imagen que compartiste, veo que:
- ✅ `pdo` está marcado (correcto)
- ❌ `pdo_mysql` NO está marcado (necesitas marcarlo)

**Debes marcar `pdo_mysql` para que la aplicación pueda conectarse a MySQL.**

## 📦 Paquetes que requieren extensiones específicas

- **barryvdh/laravel-dompdf**: Requiere `gd`, `dom`, `mbstring`
- **darkaonline/l5-swagger**: Requiere `json`, `xml`
- **google/apiclient**: Requiere `curl`, `openssl`
- **doctrine/dbal**: Requiere `pdo`, `pdo_mysql`

## 🚀 Después de habilitar

1. Guarda los cambios en cPanel
2. Espera unos segundos para que se apliquen
3. Recarga tu aplicación
4. Verifica que el error de PDO haya desaparecido

