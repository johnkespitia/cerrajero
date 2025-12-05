<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cancelación de Reserva</title>
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
            background-color: #dc3545;
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
            border-left: 4px solid #dc3545;
        }
        .cancellation-box {
            background-color: #f8d7da;
            border: 1px solid #dc3545;
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
            <h2>Cancelación de Reserva</h2>
        </div>
        <div class="content">
            <p>Hola {{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }},</p>

            <div class="cancellation-box">
                <strong>Tu reserva ha sido cancelada</strong><br>
                Te confirmamos la cancelación de tu reserva #{{ $reservation->reservation_number }}
            </div>

            <div class="info-box">
                <h3>Detalles de la Reserva Cancelada</h3>
                <p>
                    <strong>Número de Reserva:</strong> {{ $reservation->reservation_number }}<br>
                    <strong>Fecha de Check-in:</strong> {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}<br>
                    @if($reservation->check_out_date)
                        <strong>Fecha de Check-out:</strong> {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}<br>
                    @endif
                    @if($reservation->room)
                        <strong>Habitación:</strong> {{ $reservation->room->name }}<br>
                    @elseif($reservation->roomType)
                        <strong>Tipo de Habitación:</strong> {{ $reservation->roomType->name }}<br>
                    @endif
                    <strong>Total de la Reserva:</strong> ${{ number_format($reservation->total_price ?? 0, 2) }}<br>
                    @if($reservation->cancellation_reason)
                        <strong>Razón de Cancelación:</strong> {{ $reservation->cancellation_reason }}<br>
                    @endif
                </p>
            </div>

            @if($reservation->payment_status === 'refunded' || $reservation->payment_status === 'partial')
                <p><strong>Información de Reembolso:</strong></p>
                <ul>
                    @if($reservation->payment_status === 'refunded')
                        <li>Tu reembolso ha sido procesado</li>
                    @else
                        <li>Se procesará el reembolso según nuestra política de cancelación</li>
                    @endif
                    <li>El tiempo de procesamiento puede variar según el método de pago utilizado</li>
                    <li>Si tienes preguntas sobre el reembolso, por favor contáctanos</li>
                </ul>
            @endif

            <p>Lamentamos que no puedas visitarnos en esta ocasión. Esperamos poder recibirte en el futuro.</p>

            <p>Si deseas hacer una nueva reserva o tienes alguna pregunta, no dudes en contactarnos.</p>
        </div>
        <div class="footer">
            <p>Atentamente,<br>
            <strong>Equipo de Campo Verde</strong></p>
            <p>Esperamos verte pronto.</p>
        </div>
    </div>
</body>
</html>

