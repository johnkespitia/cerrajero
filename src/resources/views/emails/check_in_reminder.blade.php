@extends('emails.layouts.base')

@section('title', 'Recordatorio de Check-in - Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>RECORDATORIO CHECK-IN</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Hola <strong>{{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }}</strong>,</p>

    <p>Te recordamos que tu reserva en <strong>Campo Verde</strong> está programada para mañana.</p>

    <div class="info-block">
        <h3>Detalles de tu Reserva #{{ $reservation->reservation_number }}</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
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
                <div class="info-label">HUÉSPEDES:</div>
            </div>
            <div class="info-block-right">
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
                <div class="info-value">
                    {{ $reservation->adults }} adultos
                    @if($reservation->children > 0)
                        , {{ $reservation->children }} niños
                    @endif
                    @if($reservation->infants > 0)
                        , {{ $reservation->infants }} bebés
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="info-block" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p><strong>⚠️ Importante:</strong></p>
        <ul>
            <li>Por favor, confirma tu hora de llegada si aún no lo has hecho</li>
            <li>Trae contigo una identificación válida</li>
            <li>Si tienes alguna solicitud especial, comunícate con nosotros antes de tu llegada</li>
        </ul>
    </div>

    <p>Si necesitas modificar o cancelar tu reserva, por favor contáctanos lo antes posible.</p>

    <p>Esperamos recibirte pronto en Campo Verde.</p>
@endsection
