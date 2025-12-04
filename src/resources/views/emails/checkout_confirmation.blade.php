<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-out Completado - {{ $reservation->reservation_number }}</title>
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
            background-color: #2d5016;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .footer {
            background-color: #f5f5f5;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-radius: 0 0 5px 5px;
        }
        .info-box {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #2d5016;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2d5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Check-out Completado</h1>
        <p>Reserva #{{ $reservation->reservation_number }}</p>
    </div>
    
    <div class="content">
        <p>Estimado/a 
            @if($customer->customer_type === 'company')
                {{ $customer->company_name }}
            @else
                {{ $customer->name }} {{ $customer->last_name }}
            @endif,
        </p>
        
        <p>Le informamos que el check-out de su reserva ha sido completado exitosamente.</p>
        
        <div class="info-box">
            <h3>Detalles de la Reserva:</h3>
            <p><strong>Número de Reserva:</strong> {{ $reservation->reservation_number }}</p>
            <p><strong>Fecha de Check-in:</strong> {{ $reservation->check_in_date->format('d/m/Y') }}</p>
            <p><strong>Fecha de Check-out:</strong> {{ $reservation->check_out_date ? $reservation->check_out_date->format('d/m/Y') : 'N/A' }}</p>
            @if($reservation->room)
                <p><strong>Habitación:</strong> {{ $reservation->room->display_name }}</p>
            @endif
        </div>
        
        <p>Adjunto encontrará el certificado de check-out con todos los detalles de su estadía, incluyendo información de visitantes y resumen de pagos.</p>
        
        <p>Esperamos haber superado sus expectativas y tener el placer de recibirlos nuevamente en Campo Verde.</p>
        
        <p>Si tiene alguna consulta o necesita asistencia adicional, no dude en contactarnos.</p>
        
        <p>¡Gracias por elegir Campo Verde!</p>
    </div>
    
    <div class="footer">
        <p><strong>Campo Verde</strong></p>
        <p>{{ config('mail.from.address', 'reservas@campoverde.com') }}</p>
        <p>Este es un email automático, por favor no responda a este mensaje.</p>
    </div>
</body>
</html>



