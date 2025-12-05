<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Check-in Confirmado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2d5016;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .info-box {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #2d5016;
        }
        .success-badge {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Check-in Confirmado</h2>
        </div>
        <div class="content">
            <p>Hola {{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }},</p>

            <div class="success-badge">
                ✓ Tu check-in ha sido confirmado exitosamente
            </div>

            <p>Te confirmamos que tu check-in para la reserva <strong>#{{ $reservation->reservation_number }}</strong> ha sido procesado correctamente.</p>

            <div class="info-box">
                <h3>Detalles de tu Estadía</h3>
                <p>
                    <strong>Número de Reserva:</strong> {{ $reservation->reservation_number }}<br>
                    <strong>Fecha de Check-in:</strong> {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}<br>
                    @if($reservation->check_in_time)
                        <strong>Hora de Check-in:</strong> {{ \Carbon\Carbon::parse($reservation->check_in_time)->format('H:i') }}<br>
                    @endif
                    @if($reservation->check_out_date)
                        <strong>Fecha de Check-out:</strong> {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}<br>
                    @endif
                    @if($reservation->room)
                        <strong>Habitación:</strong> {{ $reservation->room->name }}<br>
                    @elseif($reservation->roomType)
                        <strong>Tipo de Habitación:</strong> {{ $reservation->roomType->name }}<br>
                    @endif
                </p>
            </div>

            <p>Esperamos que disfrutes tu estadía en Campo Verde. Si necesitas algo durante tu visita, no dudes en contactarnos.</p>

            <p><strong>Información importante:</strong></p>
            <ul>
                <li>Conserva este correo como comprobante de tu check-in</li>
                <li>Recuerda la fecha y hora de tu check-out</li>
                <li>Si tienes alguna emergencia, contacta a recepción</li>
            </ul>
        </div>
        <div class="footer">
            <p>Atentamente,<br>
            <strong>Equipo de Campo Verde</strong></p>
            <p>¡Que disfrutes tu estadía!</p>
        </div>
    </div>
</body>
</html>

