<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Código de Verificación - Compra en Kiosko</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #28a745;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .otp-code {
            background-color: #fff;
            border: 3px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #28a745;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Campo Verde Centro Vacacional</h1>
        <h2>Código de Verificación</h2>
    </div>
    
    <div class="content">
        <p>Estimado/a <strong>{{ $recipient['name'] }}</strong>,</p>
        
        <p>Se ha solicitado una compra a crédito en el kiosko asociada a su reserva <strong>#{{ $reservation->reservation_number }}</strong>.</p>
        
        <p>Para autorizar esta compra, por favor ingrese el siguiente código de verificación:</p>
        
        <div class="otp-code">
            {{ $otp_code }}
        </div>
        
        <div class="warning">
            <strong>⚠️ Importante:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Este código es válido por <strong>10 minutos</strong></li>
                <li>No comparta este código con nadie</li>
                <li>Si no solicitó esta compra, ignore este mensaje</li>
            </ul>
        </div>
        
        <p><strong>Detalles de la compra:</strong></p>
        <ul>
            <li>Factura: #{{ $invoice->payment_code }}</li>
            <li>Reserva: {{ $reservation->reservation_number }}</li>
            <li>Fecha: {{ now()->format('d/m/Y H:i') }}</li>
        </ul>
        
        <p>Si tiene alguna pregunta, por favor contacte a recepción.</p>
        
        <p>Atentamente,<br>
        <strong>Campo Verde Centro Vacacional</strong></p>
    </div>
    
    <div class="footer">
        <p>Este es un mensaje automático, por favor no responda a este correo.</p>
        <p>© {{ date('Y') }} Campo Verde Centro Vacacional. Todos los derechos reservados.</p>
    </div>
</body>
</html>

