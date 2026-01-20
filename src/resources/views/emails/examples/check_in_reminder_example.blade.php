@extends('emails.layouts.base')

@section('title', 'Recordatorio de Check-in')

@section('content')
    <p>Hola <strong>Juan Pérez</strong>,</p>

    <p>Te recordamos que tu reserva en <strong>Campo Verde</strong> está programada para mañana.</p>

    <div class="info-box">
        <h3>Detalles de tu Reserva #RES202501000001</h3>
        <p>
            <strong>Fecha de Check-in:</strong> 17/01/2025<br>
            <strong>Hora de Check-in:</strong> 15:00<br>
            <strong>Fecha de Check-out:</strong> 20/01/2025<br>
            <strong>Habitación:</strong> Cabaña 201<br>
            <strong>Huéspedes:</strong> 2 adultos, 1 niño
        </p>
    </div>

    <div class="warning-box">
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
