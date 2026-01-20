@extends('emails.layouts.base')

@section('title', 'Check-out Completado - Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>CHECK-OUT COMPLETADO</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Estimado/a 
        @if($customer->customer_type === 'company')
            {{ $customer->company_name }}
        @else
            {{ $customer->name }} {{ $customer->last_name }}
        @endif,
    </p>
    
    <p>Le informamos que el check-out de su reserva ha sido completado exitosamente.</p>
    
    <div class="info-block">
        <h3>Detalles de la Reserva:</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">NÚMERO DE RESERVA:</div>
                <div class="info-label">FECHA DE CHECK-IN:</div>
                <div class="info-label">FECHA DE CHECK-OUT:</div>
                @if($reservation->room)
                    <div class="info-label">HABITACIÓN:</div>
                @endif
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $reservation->reservation_number }}</div>
                <div class="info-value">{{ $reservation->check_in_date->format('d/m/Y') }}</div>
                <div class="info-value">{{ $reservation->check_out_date ? $reservation->check_out_date->format('d/m/Y') : 'N/A' }}</div>
                @if($reservation->room)
                    <div class="info-value">{{ $reservation->room->display_name }}</div>
                @endif
            </div>
        </div>
    </div>
    
    <p>Adjunto encontrará el certificado de check-out con todos los detalles de su estadía, incluyendo información de visitantes y resumen de pagos.</p>
    
    <p>Esperamos haber superado sus expectativas y tener el placer de recibirlos nuevamente en Campo Verde.</p>
    
    <p>Si tiene alguna consulta o necesita asistencia adicional, no dude en contactarnos.</p>
    
    <p>¡Gracias por elegir Campo Verde!</p>
@endsection
