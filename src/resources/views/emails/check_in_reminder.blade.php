<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recordatorio de Check-in</title>
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
            <h2>Recordatorio de Check-in</h2>
        </div>
        <div class="content">
            <p>Hola {{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }},</p>

            <p>Te recordamos que tu reserva en <strong>Campo Verde</strong> está programada para mañana.</p>

            <div class="info-box">
                <h3>Detalles de tu Reserva #{{ $reservation->reservation_number }}</h3>
                <p>
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
                    <strong>Huéspedes:</strong> {{ $reservation->adults }} adultos,
                    @if($reservation->children > 0)
                        {{ $reservation->children }} niños,
                    @endif
                    @if($reservation->infants > 0)
                        {{ $reservation->infants }} bebés
                    @endif
                </p>
            </div>

            <p><strong>Importante:</strong></p>
            <ul>
                <li>Por favor, confirma tu hora de llegada si aún no lo has hecho</li>
                <li>Trae contigo una identificación válida</li>
                <li>Si tienes alguna solicitud especial, comunícate con nosotros antes de tu llegada</li>
            </ul>

            <p>Si necesitas modificar o cancelar tu reserva, por favor contáctanos lo antes posible.</p>

            <p>Esperamos recibirte pronto en Campo Verde.</p>
        </div>
        <div class="footer">
            <p>Atentamente,<br>
            <strong>Equipo de Campo Verde</strong></p>
            <p>Si tienes alguna pregunta, responde a este correo o contáctanos.</p>
        </div>
    </div>
</body>
</html>

