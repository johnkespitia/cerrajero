@extends('emails.layouts.base')

@section('title', 'Código de verificación - Registro de huéspedes')

@section('header-title')
    <h1>CÓDIGO DE VERIFICACIÓN</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Estimado/a <strong>{{ $recipient['name'] }}</strong>,</p>

    <p>
        Use el siguiente código para acceder al registro de huéspedes de su reserva
        <strong>#{{ $reservation->reservation_number }}</strong>:
    </p>

    <div style="background-color: #ffffff; border: 3px solid #2F6B3F; padding: 25px; text-align: center; font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #2F6B3F; margin: 25px 0; font-family: 'Courier New', monospace;">
        {{ $otp_code }}
    </div>

    <div class="info-block" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p><strong>Importante:</strong></p>
        <ul>
            <li>Este código es válido por <strong>{{ $ttl_minutes }} minutos</strong></li>
            <li>No comparta este código con nadie</li>
            <li>Si no solicitó este acceso, ignore este mensaje</li>
        </ul>
    </div>
@endsection
