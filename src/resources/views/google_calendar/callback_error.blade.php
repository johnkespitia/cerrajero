<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Autorización - Google Calendar</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
        h1 {
            color: #1f2937;
            margin-bottom: 10px;
        }
        p {
            color: #6b7280;
            line-height: 1.6;
        }
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        .error-box strong {
            color: #dc2626;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        .button:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">✗</div>
        <h1>Error de Autorización</h1>
        <div class="error-box">
            <strong>Error:</strong> {{ $error ?? 'Desconocido' }}<br>
            <strong>Descripción:</strong> {{ $error_description ?? 'No se pudo completar la autorización' }}
        </div>
        <p><strong>Posibles causas:</strong></p>
        <ul style="text-align: left; color: #6b7280;">
            <li>El Redirect URI no está autorizado en Google Cloud Console</li>
            <li>La aplicación está en modo de prueba y tu cuenta no está en la lista</li>
            <li>El tipo de aplicación OAuth no coincide</li>
        </ul>
        <p><strong>Solución:</strong></p>
        <ol style="text-align: left; color: #6b7280;">
            <li>Ve a <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
            <li>Selecciona tu proyecto</li>
            <li>Ve a "APIs y servicios" > "Credenciales"</li>
            <li>Edita tu credencial OAuth 2.0</li>
            <li>Agrega este Redirect URI: <code>{{ env('APP_URL') }}/api/google-calendar/callback</code></li>
            <li>Si está en modo de prueba, agrega tu email a "Usuarios de prueba"</li>
        </ol>
        <a href="javascript:window.close()" class="button">Cerrar Ventana</a>
    </div>
</body>
</html>

