<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificado de Reserva - {{ $reservation->reservation_number }}</title>
    <style>
        @page {
            margin: 20mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }
        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
            border: 2px solid #2d5016;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2d5016;
        }
        .logo-container {
            margin-bottom: 15px;
        }
        .logo-img {
            max-width: 150px;
            max-height: 80px;
            margin: 0 auto;
        }
        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: #2d5016;
            letter-spacing: 2px;
            margin-top: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .reservation-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2d5016;
            margin-bottom: 12px;
            padding-bottom: 5px;
            border-bottom: 1px solid #2d5016;
            text-transform: uppercase;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 40%;
            padding: 6px 10px 6px 0;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            padding: 6px 0;
            vertical-align: top;
        }
        .field {
            margin: 8px 0;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10pt;
        }
        th {
            background-color: #2d5016;
            color: #fff;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10pt;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 10pt;
        }
        .status-confirmed {
            background-color: #28a745;
            color: #fff;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }
        .price {
            font-size: 14pt;
            font-weight: bold;
            color: #2d5016;
        }
        .two-columns {
            display: table;
            width: 100%;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .column:last-child {
            padding-right: 0;
            padding-left: 20px;
        }
        .page-break {
            page-break-before: always;
        }
        .terms-section {
            margin-top: 20px;
        }
        .terms-list {
            margin-left: 20px;
            margin-top: 10px;
        }
        .terms-list li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .contact-info {
            background-color: #f0f7f0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2d5016;
            margin-top: 15px;
        }
        .contact-info strong {
            color: #2d5016;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                @if(isset($logo_base64) && $logo_base64)
                    <img src="{{ $logo_base64 }}" alt="Campo Verde" class="logo-img">
                @else
                    <div class="logo-text">CAMPO VERDE</div>
                @endif
            </div>
            <div class="title">CERTIFICADO DE RESERVA</div>
        </div>

        <div class="reservation-info">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Número de Reserva:</div>
                    <div class="info-value"><strong>{{ $reservation->reservation_number }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Fecha de Emisión:</div>
                    <div class="info-value">{{ $date }} {{ $time }}</div>
                </div>
            </div>
        </div>

        <div class="two-columns">
            <div class="column">
                <div class="section">
                    <div class="section-title">Datos del Cliente</div>
                    <div class="info-grid">
                        @if($customer->customer_type === 'company')
                            <div class="info-row">
                                <div class="info-label">Empresa:</div>
                                <div class="info-value">{{ $customer->company_name }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">NIT:</div>
                                <div class="info-value">{{ $customer->company_nit }}</div>
                            </div>
                            @if($customer->company_legal_representative)
                                <div class="info-row">
                                    <div class="info-label">Representante Legal:</div>
                                    <div class="info-value">{{ $customer->company_legal_representative }}</div>
                                </div>
                            @endif
                            @if($customer->company_address)
                                <div class="info-row">
                                    <div class="info-label">Dirección:</div>
                                    <div class="info-value">{{ $customer->company_address }}</div>
                                </div>
                            @endif
                        @else
                            <div class="info-row">
                                <div class="info-label">Nombre:</div>
                                <div class="info-value">{{ $customer->name }} {{ $customer->last_name }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Documento:</div>
                                <div class="info-value">{{ $customer->dni }}</div>
                            </div>
                        @endif
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value">{{ $customer->email }}</div>
                        </div>
                        @if($customer->phone_number)
                            <div class="info-row">
                                <div class="info-label">Teléfono:</div>
                                <div class="info-value">{{ $customer->phone_number }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="column">
                <div class="section">
                    <div class="section-title">Detalles de la Reserva</div>
                    <div class="info-grid">
                        @if($reservation->reservation_type === 'room')
                            @if($reservation->roomType)
                                <div class="info-row">
                                    <div class="info-label">Tipo de Habitación:</div>
                                    <div class="info-value">{{ $reservation->roomType->name }}</div>
                                </div>
                            @endif
                            <div class="info-row">
                                <div class="info-label">Habitación:</div>
                                <div class="info-value">
                                    @if($room)
                                        {{ $room->display_name }}
                                    @else
                                        No asignada
                                    @endif
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Check-in:</div>
                                <div class="info-value">{{ $reservation->check_in_date->format('d/m/Y') }}</div>
                            </div>
                            @if($reservation->check_out_date)
                                <div class="info-row">
                                    <div class="info-label">Check-out:</div>
                                    <div class="info-value">{{ $reservation->check_out_date->format('d/m/Y') }}</div>
                                </div>
                                @php
                                    $checkIn = \Carbon\Carbon::parse($reservation->check_in_date);
                                    $checkOut = \Carbon\Carbon::parse($reservation->check_out_date);
                                    $nights = $checkIn->diffInDays($checkOut);
                                @endphp
                                <div class="info-row">
                                    <div class="info-label">Noches:</div>
                                    <div class="info-value">{{ $nights }}</div>
                                </div>
                            @endif
                        @else
                            <div class="info-row">
                                <div class="info-label">Tipo:</div>
                                <div class="info-value">Pasadía - Día de Sol</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Fecha:</div>
                                <div class="info-value">{{ $reservation->check_in_date->format('d/m/Y') }}</div>
                            </div>
                        @endif
                        <div class="info-row">
                            <div class="info-label">Huéspedes:</div>
                            <div class="info-value">
                                {{ $reservation->adults }} adultos,
                                {{ $reservation->children }} niños,
                                {{ $reservation->infants }} bebés
                            </div>
                        </div>
                        @php $additionalTotal = $reservation->additionalServices ? $reservation->additionalServices->sum('total') : 0; @endphp
                        @if($additionalTotal > 0)
                        <div class="info-row">
                            <div class="info-label">Alojamiento:</div>
                            <div class="info-value">${{ number_format(($reservation->final_price ?? $reservation->total_price) - $additionalTotal, 0, ',', '.') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Servicios adicionales:</div>
                            <div class="info-value">${{ number_format($additionalTotal, 0, ',', '.') }}</div>
                        </div>
                        @endif
                        <div class="info-row">
                            <div class="info-label">Precio Total:</div>
                            <div class="info-value price">${{ number_format($reservation->final_price ?? $reservation->total_price, 0, ',', '.') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Estado:</div>
                            <div class="info-value">
                                <span class="status-badge status-{{ $reservation->status }}">
                                    @if($reservation->status === 'confirmed')
                                        Confirmada
                                    @elseif($reservation->status === 'pending')
                                        Pendiente
                                    @elseif($reservation->status === 'checked_in')
                                        Check-in
                                    @elseif($reservation->status === 'checked_out')
                                        Check-out
                                    @elseif($reservation->status === 'cancelled')
                                        Cancelada
                                    @else
                                        {{ ucfirst($reservation->status) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                        @if($reservation->payment_status)
                            <div class="info-row">
                                <div class="info-label">Estado de Pago:</div>
                                <div class="info-value">
                                    @if($reservation->payment_status === 'paid')
                                        Pagado
                                    @elseif($reservation->payment_status === 'partial')
                                        Pago Parcial
                                    @elseif($reservation->payment_status === 'pending')
                                        Pendiente
                                    @elseif($reservation->payment_status === 'free')
                                        Gratis
                                    @elseif($reservation->payment_status === 'refunded')
                                        Reembolsado
                                    @else
                                        {{ ucfirst($reservation->payment_status) }}
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($reservation->additionalServices && $reservation->additionalServices->count() > 0)
            <div class="section">
                <div class="section-title">Servicios adicionales contratados</div>
                <table>
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Cantidad</th>
                            <th>Huéspedes</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservation->additionalServices as $ras)
                            <tr>
                                <td>{{ optional($ras->additionalService)->name ?? 'N/A' }}</td>
                                <td>{{ $ras->quantity }}</td>
                                <td>{{ $ras->guests_count }}</td>
                                <td style="text-align: right;">${{ number_format($ras->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f0f7f0; font-weight: bold;">
                            <td colspan="3" style="text-align: right;">Subtotal servicios:</td>
                            <td style="text-align: right;">${{ number_format($reservation->additionalServices->sum('total'), 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if($reservation->guests && $reservation->guests->count() > 0)
            <div class="section">
                <div class="section-title">Huéspedes Registrados</div>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservation->guests as $guest)
                            <tr>
                                <td>
                                    <strong>{{ $guest->first_name }} {{ $guest->last_name }}</strong>
                                    @if($guest->is_primary_guest)
                                        <span style="color: #2d5016; font-size: 9pt;">(Principal)</span>
                                    @endif
                                </td>
                                <td>{{ $guest->document_number ?? 'N/A' }}</td>
                                <td>{{ $guest->document_type ?? 'N/A' }}</td>
                                <td>{{ $guest->email ?? 'N/A' }}</td>
                            </tr>
                            @if($guest->health_insurance_name)
                                <tr>
                                    <td colspan="4" style="font-size: 9pt; background-color: #f9f9f9; padding-left: 15px;">
                                        <strong>Aseguradora:</strong> {{ $guest->health_insurance_name }}
                                        @if($guest->health_insurance_type)
                                            | <strong>Tipo:</strong> {{ $guest->health_insurance_type === 'national' ? 'Nacional' : 'Internacional' }}
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($reservation->special_requests)
            <div class="section">
                <div class="section-title">Solicitudes Especiales</div>
                <div style="background-color: #f9f9f9; padding: 12px; border-radius: 5px; border-left: 4px solid #2d5016;">
                    {{ $reservation->special_requests }}
                </div>
            </div>
        @endif

        @if($reservation->free_reservation_reason)
            <div class="section">
                <div class="section-title">Información Adicional</div>
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Razón de Reserva Gratis:</div>
                        <div class="info-value">{{ $reservation->free_reservation_reason }}</div>
                    </div>
                    @if($reservation->free_reservation_reference)
                        <div class="info-row">
                            <div class="info-label">Referencia:</div>
                            <div class="info-value">{{ $reservation->free_reservation_reference }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="footer">
            <p style="margin-bottom: 10px;"><strong>Este documento certifica la reserva realizada en Campo Verde.</strong></p>
            <p style="font-size: 9pt;">Para consultas, contacte a: {{ config('mail.from.address', 'reservas@campoverde.com') }}</p>
            <p style="font-size: 9pt; margin-top: 5px;">Documento generado el {{ $date }} a las {{ $time }}</p>
        </div>
    </div>

    <!-- Segunda Página -->
    <div class="container page-break">
        <div class="header">
            <div class="logo-container">
                @if(isset($logo_base64) && $logo_base64)
                    <img src="{{ $logo_base64 }}" alt="Campo Verde" class="logo-img">
                @else
                    <div class="logo-text">CAMPO VERDE</div>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-title">Términos y Condiciones</div>
            <div class="terms-section">
                <p style="margin-bottom: 15px; text-align: justify;">
                    La presente reserva está sujeta a los siguientes términos y condiciones que el huésped acepta al realizar la reserva:
                </p>
                <ul class="terms-list">
                    <li><strong>Check-in:</strong> El horario de check-in es a partir de las 15:00 horas. Check-ins anticipados están sujetos a disponibilidad y pueden tener un costo adicional.</li>
                    <li><strong>Check-out:</strong> El horario de check-out es hasta las 12:00 horas. Check-outs tardíos están sujetos a disponibilidad y pueden tener un costo adicional.</li>
                    <li><strong>Cancelaciones:</strong> Las políticas de cancelación varían según el tipo de reserva. Por favor, consulte las condiciones específicas de su reserva.</li>
                    <li><strong>Pagos:</strong> Se requiere el pago completo o un depósito según lo acordado al momento de la reserva. Los pagos pendientes deben realizarse antes del check-in.</li>
                    <li><strong>Responsabilidad:</strong> Campo Verde no se hace responsable por objetos personales perdidos o dañados durante la estadía.</li>
                    <li><strong>Reglas del establecimiento:</strong> Se espera que los huéspedes respeten las reglas del establecimiento y mantengan un comportamiento apropiado.</li>
                    <li><strong>Daños:</strong> El huésped será responsable por cualquier daño causado a las instalaciones o equipamiento durante su estadía.</li>
                    <li><strong>Modificaciones:</strong> Las modificaciones a la reserva están sujetas a disponibilidad y pueden tener restricciones o costos adicionales.</li>
                </ul>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Políticas de Cancelación</div>
            <div class="terms-section">
                <ul class="terms-list">
                    <li><strong>Cancelación con más de 7 días de anticipación:</strong> Reembolso completo o sin penalización.</li>
                    <li><strong>Cancelación entre 3 y 7 días de anticipación:</strong> Se retiene el 50% del depósito o se aplica una penalización del 50%.</li>
                    <li><strong>Cancelación con menos de 3 días de anticipación:</strong> No hay reembolso. Se cobra el 100% del valor de la reserva.</li>
                    <li><strong>No presentación (No-show):</strong> Se cobra el 100% del valor de la reserva.</li>
                    <li><strong>Reservas de temporada alta:</strong> Pueden tener políticas de cancelación más estrictas. Consulte al momento de la reserva.</li>
                </ul>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información de Contacto</div>
            <div class="contact-info">
                <p style="margin-bottom: 8px;"><strong>Campo Verde</strong></p>
                <p style="margin-bottom: 5px;"><strong>Email:</strong> {{ config('mail.from.address', 'reservas@campoverde.com') }}</p>
                <p style="margin-bottom: 5px;"><strong>Teléfono:</strong> {{ config('app.phone', 'Contactar para información') }}</p>
                <p style="margin-bottom: 5px;"><strong>Dirección:</strong> {{ config('app.address', 'Contactar para información') }}</p>
                <p style="margin-bottom: 5px;"><strong>Horario de Atención:</strong> {{ config('app.business_hours', 'Lunes a Domingo: 8:00 AM - 8:00 PM') }}</p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Instrucciones de Check-in</div>
            <div class="terms-section">
                <ul class="terms-list">
                    <li>Presentarse en la recepción con el documento de identidad del huésped principal.</li>
                    <li>Presentar este certificado de reserva (impreso o digital).</li>
                    <li>Completar el registro de huéspedes si aplica.</li>
                    <li>Realizar el pago pendiente si existe.</li>
                    <li>Recibir las llaves y orientación sobre las instalaciones.</li>
                </ul>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Servicios Adicionales</div>
            <div class="terms-section">
                <p style="margin-bottom: 10px;">Campo Verde ofrece los siguientes servicios adicionales:</p>
                <ul class="terms-list">
                    <li>Restaurante y bar</li>
                    <li>Servicios de spa y bienestar</li>
                    <li>Actividades recreativas</li>
                    <li>Servicio de lavandería</li>
                    <li>Wi-Fi gratuito</li>
                    <li>Estacionamiento</li>
                </ul>
                <p style="margin-top: 15px; font-size: 9pt; font-style: italic;">
                    Los servicios adicionales pueden tener costos adicionales. Consulte en recepción para más información.
                </p>
            </div>
        </div>

        <div class="footer">
            <p style="font-size: 9pt; margin-top: 20px;">
                <strong>Nota Importante:</strong> Este documento es una confirmación de su reserva. 
                Por favor, consérvelo y preséntelo al momento del check-in.
            </p>
            <p style="font-size: 9pt; margin-top: 10px;">
                Para cualquier consulta o modificación de su reserva, contacte a Campo Verde 
                utilizando la información de contacto proporcionada arriba.
            </p>
        </div>
    </div>
</body>
</html>



