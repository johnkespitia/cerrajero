@extends('emails.layouts.base')

@section('title', 'Check-in Confirmado - Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>CHECK-IN CONFIRMADO</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Hola <strong>{{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }}</strong>,</p>

    <div class="info-block" style="background-color: #e8f5e9; border-left-color: #4caf50;">
        <p style="font-size: 16px; font-weight: 700; color: #111111; margin: 0;">✓ Tu check-in ha sido confirmado exitosamente</p>
    </div>

    <p>Te confirmamos que tu check-in para la reserva <strong>#{{ $reservation->reservation_number }}</strong> ha sido procesado correctamente.</p>

    <div class="info-block">
        <h3>Detalles de tu Estadía</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">NÚMERO DE RESERVA:</div>
                <div class="info-label">FECHA DE CHECK-IN:</div>
                @if($reservation->check_in_time)
                    <div class="info-label">HORA DE CHECK-IN:</div>
                @endif
                @if($reservation->check_out_date)
                    <div class="info-label">FECHA DE CHECK-OUT:</div>
                @endif
                @if($reservation->room)
                    <div class="info-label">HABITACIÓN:</div>
                @elseif($reservation->roomType)
                    <div class="info-label">TIPO DE HABITACIÓN:</div>
                @endif
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $reservation->reservation_number }}</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}</div>
                @if($reservation->check_in_time)
                    <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_in_time)->format('H:i') }}</div>
                @endif
                @if($reservation->check_out_date)
                    <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}</div>
                @endif
                @if($reservation->room)
                    <div class="info-value">{{ $reservation->room->name }}</div>
                @elseif($reservation->roomType)
                    <div class="info-value">{{ $reservation->roomType->name }}</div>
                @endif
            </div>
        </div>
    </div>

    <p>Esperamos que disfrutes tu estadía en Campo Verde. Si necesitas algo durante tu visita, no dudes en contactarnos.</p>

    <div class="info-block">
        <p><strong>Información importante:</strong></p>
        <ul>
            <li>Conserva este correo como comprobante de tu check-in</li>
            <li>Recuerda la fecha y hora de tu check-out</li>
            <li>Si tienes alguna emergencia, contacta a recepción</li>
        </ul>
    </div>
@endsection
