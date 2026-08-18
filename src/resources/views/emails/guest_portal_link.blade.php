@extends('emails.layouts.base')

@section('title', 'Registro de huéspedes - Campo Verde')

@section('header-title')
    <h1>REGISTRO DE HUÉSPEDES</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Estimado/a <strong>{{ $recipient['name'] }}</strong>,</p>

    <p>
        Para agilizar su llegada, puede completar los datos de los huéspedes de su reserva
        <strong>#{{ $reservation->reservation_number }}</strong> antes del check-in.
    </p>

    <div style="text-align: center; margin: 28px 0;">
        <a href="{{ $portalUrl }}"
           style="display: inline-block; background-color: #2F6B3F; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 4px; font-weight: 600;">
            Completar datos de huéspedes
        </a>
    </div>

    <p style="word-break: break-all; font-size: 13px; color: #555;">
        Si el botón no funciona, copie y pegue este enlace:<br>
        <a href="{{ $portalUrl }}">{{ $portalUrl }}</a>
    </p>

    <div class="info-block" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p><strong>Importante:</strong></p>
        <ul>
            <li>Al abrir el enlace deberá solicitar un código de verificación enviado a este correo.</li>
            <li>No comparta el enlace ni el código con personas no autorizadas.</li>
            <li>Si no solicitó este acceso, ignore este mensaje.</li>
        </ul>
    </div>
@endsection
