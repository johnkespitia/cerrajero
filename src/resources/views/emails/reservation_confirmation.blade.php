<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirmación de Reserva</title>
</head>
<body>
    <h2>Confirmación de Reserva #{{ $reservation->reservation_number }}</h2>

    <p>Hola {{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }},</p>

    <p>Gracias por reservar en <strong>Campo Verde</strong>. Adjuntamos el certificado en PDF con todos los detalles de tu reserva.</p>

    <p>
        <strong>Check-in:</strong> {{ $reservation->check_in_date->format('d/m/Y') }}<br>
        @if($reservation->check_out_date)
            <strong>Check-out:</strong> {{ $reservation->check_out_date->format('d/m/Y') }}<br>
        @endif
        <strong>Huéspedes:</strong> {{ $reservation->adults }} adultos,
        {{ $reservation->children }} niños,
        {{ $reservation->infants }} bebés<br>
        <strong>Total:</strong> ${{ number_format($reservation->total_price, 2) }}
    </p>

    <p>Si tienes alguna pregunta o necesitas modificar tu reserva, por favor responde a este correo.</p>

    <p>Atentamente,<br>
        Equipo de Campo Verde</p>
</body>
</html>



