<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recordatorio de Check-out</title>
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
        .reminder-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
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
            <h2>Recordatorio de Check-out</h2>
        </div>
        <div class="content">
            <p>Hola {{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }},</p>

            <p>Te recordamos que hoy es tu fecha de check-out para la reserva <strong>#{{ $reservation->reservation_number }}</strong>.</p>

            <div class="reminder-box">
                <strong>⚠️ Recordatorio Importante:</strong><br>
                Por favor, asegúrate de completar tu check-out antes de la hora indicada.
            </div>

            <div class="info-box">
                <h3>Detalles de Check-out</h3>
                <p>
                    <strong>Número de Reserva:</strong> {{ $reservation->reservation_number }}<br>
                    <strong>Fecha de Check-out:</strong> {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}<br>
                    @if($reservation->check_out_time)
                        <strong>Hora de Check-out:</strong> {{ \Carbon\Carbon::parse($reservation->check_out_time)->format('H:i') }}<br>
                    @else
                        <strong>Hora de Check-out:</strong> Por favor, consulta con recepción<br>
                    @endif
                    @if($reservation->room)
                        <strong>Habitación:</strong> {{ $reservation->room->name }}<br>
                    @endif
                </p>
            </div>

            <p><strong>Antes de tu salida:</strong></p>
            <ul>
                <li>Revisa que no hayas dejado pertenencias personales en la habitación</li>
                <li>Verifica que todos los servicios adicionales estén pagados</li>
                <li>Devuelve las llaves o tarjetas de acceso en recepción</li>
                <li>Si tienes algún cargo pendiente, por favor salda tu cuenta</li>
            </ul>

            <p>Esperamos que hayas disfrutado tu estadía en Campo Verde. ¡Fue un placer recibirte!</p>

            <p>Si necesitas extender tu estadía o tienes alguna pregunta, por favor contacta a recepción lo antes posible.</p>
        </div>
        <div class="footer">
            <p>Atentamente,<br>
            <strong>Equipo de Campo Verde</strong></p>
            <p>¡Esperamos verte pronto de nuevo!</p>
        </div>
    </div>
</body>
</html>

