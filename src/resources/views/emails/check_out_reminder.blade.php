@extends('emails.layouts.base')

@section('title', 'Recordatorio de Check-out - Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>RECORDATORIO CHECK-OUT</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Hola <strong>{{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }}</strong>,</p>

    <p>Te recordamos que hoy es tu fecha de check-out para la reserva <strong>#{{ $reservation->reservation_number }}</strong>.</p>

    <div class="info-block" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p><strong>⚠️ Recordatorio Importante:</strong><br>
        Por favor, asegúrate de completar tu check-out antes de la hora indicada.</p>
    </div>

    <div class="info-block">
        <h3>Detalles de Check-out</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">NÚMERO DE RESERVA:</div>
                <div class="info-label">FECHA DE CHECK-OUT:</div>
                @if($reservation->check_out_time)
                    <div class="info-label">HORA DE CHECK-OUT:</div>
                @else
                    <div class="info-label">HORA DE CHECK-OUT:</div>
                @endif
                @if($reservation->room)
                    <div class="info-label">HABITACIÓN:</div>
                @endif
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $reservation->reservation_number }}</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}</div>
                @if($reservation->check_out_time)
                    <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_out_time)->format('H:i') }}</div>
                @else
                    <div class="info-value">Por favor, consulta con recepción</div>
                @endif
                @if($reservation->room)
                    <div class="info-value">{{ $reservation->room->name }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="info-block">
        <p><strong>Antes de tu salida:</strong></p>
        <ul>
            <li>Revisa que no hayas dejado pertenencias personales en la habitación</li>
            <li>Verifica que todos los servicios adicionales estén pagados</li>
            <li>Devuelve las llaves o tarjetas de acceso en recepción</li>
            <li>Si tienes algún cargo pendiente, por favor salda tu cuenta</li>
        </ul>
    </div>

    <p>Esperamos que hayas disfrutado tu estadía en Campo Verde. ¡Fue un placer recibirte!</p>

    <p>Si necesitas extender tu estadía o tienes alguna pregunta, por favor contacta a recepción lo antes posible.</p>
@endsection
