@extends('emails.layouts.base')

@section('title', 'Código de Verificación - Compra en Kiosko')

@section('header-title')
    <h1>CÓDIGO DE VERIFICACIÓN</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Estimado/a <strong>{{ $recipient['name'] }}</strong>,</p>
    
    <p>Se ha solicitado una compra a crédito en el kiosko asociada a su reserva <strong>#{{ $reservation->reservation_number }}</strong>.</p>
    
    <p>Para autorizar esta compra, por favor ingrese el siguiente código de verificación:</p>
    
    <div style="background-color: #ffffff; border: 3px solid #2F6B3F; padding: 25px; text-align: center; font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #2F6B3F; margin: 25px 0; font-family: 'Courier New', monospace;">
        {{ $otp_code }}
    </div>
    
    <div class="info-block" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <p><strong>⚠️ Importante:</strong></p>
        <ul>
            <li>Este código es válido por <strong>10 minutos</strong></li>
            <li>No comparta este código con nadie</li>
            <li>Si no solicitó esta compra, ignore este mensaje</li>
        </ul>
    </div>
    
    <div class="info-block">
        <h3>Detalles de la compra:</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">FACTURA:</div>
                <div class="info-label">RESERVA:</div>
                <div class="info-label">FECHA:</div>
            </div>
            <div class="info-block-right">
                <div class="info-value">#{{ $invoice->payment_code }}</div>
                <div class="info-value">{{ $reservation->reservation_number }}</div>
                <div class="info-value">{{ now()->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>
    
    <p>Si tiene alguna pregunta, por favor contacte a recepción.</p>
@endsection
