@extends('emails.layouts.base')

@section('title', 'Cancelación de Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>CANCELACIÓN RESERVA</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Hola <strong>{{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }}</strong>,</p>

    <div class="info-block" style="background-color: #f8d7da; border-left-color: #dc3545;">
        <p><strong>Tu reserva ha sido cancelada</strong><br>
        Te confirmamos la cancelación de tu reserva #{{ $reservation->reservation_number }}</p>
    </div>

    <div class="info-block">
        <h3>Detalles de la Reserva Cancelada</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">NÚMERO DE RESERVA:</div>
                <div class="info-label">FECHA DE CHECK-IN:</div>
                @if($reservation->check_out_date)
                    <div class="info-label">FECHA DE CHECK-OUT:</div>
                @endif
                @if($reservation->room)
                    <div class="info-label">HABITACIÓN:</div>
                @elseif($reservation->roomType)
                    <div class="info-label">TIPO DE HABITACIÓN:</div>
                @endif
                <div class="info-label">TOTAL DE LA RESERVA:</div>
                @if($reservation->cancellation_reason)
                    <div class="info-label">RAZÓN DE CANCELACIÓN:</div>
                @endif
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $reservation->reservation_number }}</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}</div>
                @if($reservation->check_out_date)
                    <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}</div>
                @endif
                @if($reservation->room)
                    <div class="info-value">{{ $reservation->room->name }}</div>
                @elseif($reservation->roomType)
                    <div class="info-value">{{ $reservation->roomType->name }}</div>
                @endif
                <div class="info-value">${{ number_format($reservation->total_price ?? 0, 2) }}</div>
                @if($reservation->cancellation_reason)
                    <div class="info-value">{{ $reservation->cancellation_reason }}</div>
                @endif
            </div>
        </div>
    </div>

    @if($reservation->payment_status === 'refunded' || $reservation->payment_status === 'partial')
        <div class="info-block">
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
        </div>
    @endif

    <p>Lamentamos que no puedas visitarnos en esta ocasión. Esperamos poder recibirte en el futuro.</p>

    <p>Si deseas hacer una nueva reserva o tienes alguna pregunta, no dudes en contactarnos.</p>
@endsection
