@extends('emails.layouts.base')

@section('title', 'Actualización de Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>ACTUALIZACIÓN RESERVA</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Hola <strong>{{ $customer->display_name ?? ($customer->name . ' ' . $customer->last_name) }}</strong>,</p>

    <p>Te informamos que se han realizado cambios en tu reserva <strong>#{{ $reservation->reservation_number }}</strong>.</p>

    @if(!empty($changes))
        <div class="info-block" style="background-color: #e7f3ff; border-left-color: #0066cc;">
            <h3>Cambios Realizados:</h3>
            @foreach($changes as $field => $change)
                <div style="padding: 8px 0; border-bottom: 1px solid #D9D9D9;">
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

    <div class="info-block">
        <h3>Detalles Actualizados de tu Reserva</h3>
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
                @if($reservation->check_out_time)
                    <div class="info-label">HORA DE CHECK-OUT:</div>
                @endif
                @if($reservation->room)
                    <div class="info-label">HABITACIÓN:</div>
                @elseif($reservation->roomType)
                    <div class="info-label">TIPO DE HABITACIÓN:</div>
                @endif
                <div class="info-label">HUÉSPEDES:</div>
                <div class="info-label">ESTADO:</div>
                <div class="info-label">TOTAL:</div>
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
                @if($reservation->check_out_time)
                    <div class="info-value">{{ \Carbon\Carbon::parse($reservation->check_out_time)->format('H:i') }}</div>
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
                <div class="info-value">
                    @if($reservation->status === 'confirmed')
                        Confirmada
                    @elseif($reservation->status === 'checked_in')
                        Check-in Realizado
                    @elseif($reservation->status === 'checked_out')
                        Check-out Realizado
                    @else
                        {{ ucfirst($reservation->status) }}
                    @endif
                </div>
                <div class="info-value">${{ number_format($reservation->final_price ?? $reservation->total_price ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <p>Si tienes alguna pregunta sobre estos cambios o necesitas realizar alguna modificación adicional, por favor contáctanos.</p>

    <p>Si no realizaste estos cambios y no los reconoces, por favor contáctanos inmediatamente.</p>
@endsection
