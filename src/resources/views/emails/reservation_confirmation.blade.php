@extends('emails.layouts.base')

@section('title', 'Confirmación de Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>CONFIRMACIÓN RESERVA</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Hola <strong>{{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }}</strong>,</p>

    <p>Gracias por reservar en <strong>Campo Verde</strong>. Adjuntamos el certificado en PDF con todos los detalles de tu reserva.</p>

    <div class="info-block">
        <h3>Detalles de tu Reserva</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">FECHA DE CHECK-IN:</div>
                @if($reservation->check_out_date)
                    <div class="info-label">FECHA DE CHECK-OUT:</div>
                @endif
                @if($isMultiRoom ?? false)
                    <div class="info-label">HABITACIONES:</div>
                @endif
                <div class="info-label">HUÉSPEDES:</div>
                <div class="info-label">TOTAL:</div>
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $reservation->check_in_date->format('d/m/Y') }}</div>
                @if($reservation->check_out_date)
                    <div class="info-value">{{ $reservation->check_out_date->format('d/m/Y') }}</div>
                @endif
                @if($isMultiRoom ?? false)
                    <div class="info-value">{{ isset($allRooms) ? $allRooms->map(fn($r) => $r->display_name ?? $r->number ?? $r->name ?? 'N/A')->join(', ') : '—' }}</div>
                @endif
                <div class="info-value">{{ $totalAdults ?? $reservation->adults }} adultos, {{ $totalChildren ?? $reservation->children }} niños, {{ $totalInfants ?? $reservation->infants }} bebés</div>
                <div class="info-value">${{ number_format($totalPrice ?? $reservation->total_price ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <p>Si tienes alguna pregunta o necesitas modificar tu reserva, por favor responde a este correo.</p>
@endsection
