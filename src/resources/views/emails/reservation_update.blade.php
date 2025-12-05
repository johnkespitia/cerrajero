<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Actualización de Reserva</title>
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
        .changes-box {
            background-color: #e7f3ff;
            border: 1px solid #0066cc;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .change-item {
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .change-item:last-child {
            border-bottom: none;
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
            <h2>Actualización de Reserva</h2>
        </div>
        <div class="content">
            <p>Hola {{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }},</p>

            <p>Te informamos que se han realizado cambios en tu reserva <strong>#{{ $reservation->reservation_number }}</strong>.</p>

            @if(!empty($changes))
                <div class="changes-box">
                    <h3>Cambios Realizados:</h3>
                    @foreach($changes as $field => $change)
                        <div class="change-item">
                            <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong><br>
                            @if(isset($change['old']))
                                <span style="color: #dc3545;">Anterior: {{ $change['old'] }}</span><br>
                            @endif
                            @if(isset($change['new']))
                                <span style="color: #28a745;">Nuevo: {{ $change['new'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="info-box">
                <h3>Detalles Actualizados de tu Reserva</h3>
                <p>
                    <strong>Número de Reserva:</strong> {{ $reservation->reservation_number }}<br>
                    <strong>Fecha de Check-in:</strong> {{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}<br>
                    @if($reservation->check_in_time)
                        <strong>Hora de Check-in:</strong> {{ \Carbon\Carbon::parse($reservation->check_in_time)->format('H:i') }}<br>
                    @endif
                    @if($reservation->check_out_date)
                        <strong>Fecha de Check-out:</strong> {{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}<br>
                    @endif
                    @if($reservation->check_out_time)
                        <strong>Hora de Check-out:</strong> {{ \Carbon\Carbon::parse($reservation->check_out_time)->format('H:i') }}<br>
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
                    <br>
                    <strong>Estado:</strong> 
                    @if($reservation->status === 'confirmed')
                        Confirmada
                    @elseif($reservation->status === 'checked_in')
                        Check-in Realizado
                    @elseif($reservation->status === 'checked_out')
                        Check-out Realizado
                    @else
                        {{ ucfirst($reservation->status) }}
                    @endif
                    <br>
                    <strong>Total:</strong> ${{ number_format($reservation->final_price ?? $reservation->total_price ?? 0, 2) }}
                </p>
            </div>

            <p>Si tienes alguna pregunta sobre estos cambios o necesitas realizar alguna modificación adicional, por favor contáctanos.</p>

            <p>Si no realizaste estos cambios y no los reconoces, por favor contáctanos inmediatamente.</p>
        </div>
        <div class="footer">
            <p>Atentamente,<br>
            <strong>Equipo de Campo Verde</strong></p>
            <p>Si tienes alguna pregunta, responde a este correo o contáctanos.</p>
        </div>
    </div>
</body>
</html>

