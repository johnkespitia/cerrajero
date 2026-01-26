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
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #111111;
            background: #FFFFFF;
        }
        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 24px;
        }
        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #D9D9D9;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 30%;
        }
        .logo-img {
            max-width: 100px;
            max-height: 100px;
            width: auto;
            height: auto;
        }
        .header-title {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 40%;
        }
        .header-title h1 {
            font-family: 'DejaVu Serif', Georgia, "Times New Roman", Times, serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #111111;
            margin: 0;
            text-transform: uppercase;
        }
        .header-reservation {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
        }
        .reservation-chip {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #111111;
            text-transform: uppercase;
        }
        .subtext {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #333333;
            margin-top: 4px;
        }
        /* Secciones */
        .section {
            margin-bottom: 24px;
        }
        .section-title {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #D9D9D9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Bloques de información */
        .info-block {
            margin: 16px 0;
            padding: 16px;
            border: 1px solid #D9D9D9;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: 700;
            width: 40%;
            padding: 8px 10px 8px 0;
            vertical-align: top;
            color: #333333;
            font-size: 11px;
        }
        .info-value {
            display: table-cell;
            padding: 8px 0;
            vertical-align: top;
            color: #111111;
            font-size: 11px;
        }
        /* Dos columnas */
        .two-columns {
            display: table;
            width: 100%;
            margin: 16px 0;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }
        .column:last-child {
            padding-right: 0;
            padding-left: 16px;
        }
        /* Tablas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 11px;
        }
        th {
            background-color: #F5F5F5;
            color: #111111;
            padding: 10px 8px;
            text-align: left;
            font-weight: 700;
            border-bottom: 1px solid #D9D9D9;
            font-size: 11px;
        }
        td {
            border-bottom: 1px solid #D9D9D9;
            padding: 10px 8px;
            text-align: left;
            color: #111111;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: 700;
            background-color: #F9F9F9;
        }
        /* Resumen financiero */
        .summary-box {
            margin: 24px 0;
            padding: 16px;
            border: 1px solid #D9D9D9;
        }
        .summary-item {
            display: table;
            width: 100%;
            margin: 8px 0;
        }
        .summary-label {
            display: table-cell;
            font-weight: 700;
            width: 60%;
            color: #111111;
        }
        .summary-value {
            display: table-cell;
            text-align: right;
            font-weight: 700;
            font-size: 14px;
            color: #111111;
        }
        /* Nota */
        .note-box {
            margin: 24px 0;
            padding: 16px;
            background-color: #F9F9F9;
            border-left: 3px solid #2F6B3F;
            font-size: 11px;
            color: #333333;
        }
        /* Divisor */
        .divider {
            border-top: 1px solid #D9D9D9;
            margin: 24px 0;
        }
        /* Footer */
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #D9D9D9;
            text-align: center;
            font-size: 9px;
            color: #333333;
        }
        .footer-logo {
            max-width: 56px;
            max-height: 56px;
            margin: 0 auto 8px;
        }
        .footer-brand {
            font-weight: 700;
            color: #111111;
            margin-bottom: 8px;
        }
        .footer-contact {
            font-size: 9px;
            color: #333333;
            margin: 4px 0;
        }
        .footer-legal {
            font-size: 9px;
            color: #666666;
            margin-top: 12px;
        }
        /* Paginación */
        .page-number {
            position: fixed;
            bottom: 20mm;
            right: 20mm;
            font-size: 9px;
            color: #666666;
        }
        /* Listas */
        ul {
            margin: 12px 0;
            padding-left: 20px;
        }
        li {
            margin: 6px 0;
            line-height: 1.5;
            color: #333333;
        }
        strong {
            color: #111111;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Página 1: Resumen tipo "invoice" -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-logo">
                @if(isset($logo_base64) && $logo_base64)
                    <img src="{{ $logo_base64 }}" alt="Campo Verde" class="logo-img">
                @else
                    <div style="font-family: 'DejaVu Serif', serif; font-size: 14px; color: #2F6B3F; font-weight: 700;">Campo Verde</div>
                @endif
            </div>
            <div class="header-title">
                <h1>CERTIFICADO DE RESERVA</h1>
                <div class="subtext">Fecha de emisión: {{ $date }} {{ $time }}</div>
            </div>
            <div class="header-reservation">
                <div class="reservation-chip">RESERVA: {{ $reservation->reservation_number }}</div>
            </div>
        </div>

        <!-- Bloque 2 columnas: Datos del cliente y Detalles de la reserva -->
        <div class="two-columns">
            <div class="column">
                <div class="section">
                    <div class="section-title">Datos del Cliente</div>
                    <div class="info-block">
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
                                <div class="info-row">
                                    <div class="info-label">RNT:</div>
                                    <div class="info-value">150590</div>
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
            </div>

            <div class="column">
                <div class="section">
                    <div class="section-title">Detalles de la Reserva</div>
                    <div class="info-block">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de huéspedes registrados -->
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
                                        <span style="color: #2F6B3F; font-size: 9px;">(Principal)</span>
                                    @endif
                                </td>
                                <td>{{ $guest->document_number ?? 'N/A' }}</td>
                                <td>{{ $guest->document_type ?? 'N/A' }}</td>
                                <td>{{ $guest->email ?? 'N/A' }}</td>
                            </tr>
                            @if($guest->health_insurance_name)
                                <tr>
                                    <td colspan="4" style="font-size: 9px; background-color: #F9F9F9; padding-left: 15px;">
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

        @if($reservation->additionalServices && $reservation->additionalServices->count() > 0)
            <div class="section">
                <div class="section-title">Servicios adicionales contratados</div>
                <table>
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Huéspedes</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservation->additionalServices as $ras)
                            <tr>
                                <td>{{ optional($ras->additionalService)->name ?? 'N/A' }}</td>
                                <td class="text-center">{{ $ras->quantity }}</td>
                                <td class="text-center">{{ $ras->guests_count }}</td>
                                <td class="text-right">${{ number_format($ras->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" class="text-right"><strong>Subtotal servicios:</strong></td>
                            <td class="text-right"><strong>${{ number_format($reservation->additionalServices->sum('total'), 0, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <!-- Resumen financiero -->
        <div class="summary-box">
            <div class="summary-item">
                <div class="summary-label">Precio Total:</div>
                <div class="summary-value">${{ number_format($reservation->final_price ?? $reservation->total_price, 0, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Estado:</div>
                <div class="summary-value">
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
                </div>
            </div>
            @if($reservation->payment_status)
                <div class="summary-item">
                    <div class="summary-label">Estado de Pago:</div>
                    <div class="summary-value">
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

        <!-- Mini bloque nota -->
        <div class="note-box">
            <strong>Este documento certifica la reserva realizada en Campo Verde Centro Vacacional.</strong>
            Para consultas, contacte a: {{ config('mail.from.address', 'c.vacacionalcampoverde@gmail.com') }}
        </div>

        @if($reservation->special_requests)
            <div class="section">
                <div class="section-title">Solicitudes Especiales</div>
                <div class="info-block">
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

        <!-- Footer -->
        <div class="footer">
            <div class="divider"></div>
            @if(isset($logo_base64) && $logo_base64)
                <img src="{{ $logo_base64 }}" alt="Campo Verde" class="footer-logo">
            @endif
            <div class="footer-brand">Campo Verde</div>
            <div class="footer-contact">Teléfono: 322 614 3787</div>
            <div class="footer-contact">Email: c.vacacionalcampoverde@gmail.com</div>
            <div class="footer-contact" style="margin-top: 12px;">
                <strong>Síguenos en:</strong><br>
                <a href="https://www.instagram.com/campoverdecocorna" style="color: #2F6B3F; text-decoration: none; margin-right: 12px;">Instagram</a>
                <a href="https://www.facebook.com/centrovacacional.campoverde" style="color: #2F6B3F; text-decoration: none; margin-right: 12px;">Facebook</a>
                <a href="https://www.youtube.com/@centrovacacionalcampoverde4511" style="color: #2F6B3F; text-decoration: none;">YouTube</a>
            </div>
            <div class="footer-legal">Documento generado el {{ $date }} a las {{ $time }}</div>
        </div>
    </div>

    <!-- Página 2: Términos y condiciones -->
    <div class="container page-break">
        <div class="header">
            <div class="header-logo">
                @if(isset($logo_base64) && $logo_base64)
                    <img src="{{ $logo_base64 }}" alt="Campo Verde" class="logo-img">
                @else
                    <div style="font-family: 'DejaVu Serif', serif; font-size: 14px; color: #2F6B3F; font-weight: 700;">Campo Verde</div>
                @endif
            </div>
            <div class="header-title">
                <h1>POLÍTICAS GENERALES</h1>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Políticas Generales – Centro Vacacional Campo Verde</div>
            <div style="text-align: justify; color: #333333; font-size: 11px; line-height: 1.6;">
                <p style="margin-bottom: 12px;">
                    El Centro Vacacional Campo Verde da la bienvenida a todos sus huéspedes y visitantes. Con el propósito de garantizar una excelente experiencia, mantener la armonía y cumplir con las disposiciones legales vigentes, establecemos las siguientes políticas de reservas, convivencia y uso de nuestras instalaciones.
                </p>

                <h2 style="font-size: 12px; font-weight: 700; color: #111111; margin-top: 16px; margin-bottom: 8px;">1. Políticas de Reservas y Pagos</h2>
                <ul>
                    <li>Para confirmar una reserva se requiere un anticipo del 50% del valor total.</li>
                    <li>El saldo restante deberá cancelarse al momento del ingreso al establecimiento.</li>
                    <li>No se realizan reembolsos bajo ninguna circunstancia.</li>
                    <li>Las solicitudes de reprogramación deberán realizarse con un mínimo de 8 días de anticipación a la fecha de ingreso.</li>
                    <li>Las reservas podrán reprogramarse de la siguiente manera:
                        <ul style="margin-top: 6px;">
                            <li>Día de sol: dentro de los 6 meses siguientes a la fecha inicial.</li>
                            <li>Hospedajes (habitaciones o villas): dentro de los 3 meses siguientes a la fecha inicial.</li>
                        </ul>
                    </li>
                    <li>Las reprogramaciones estarán sujetas a la disponibilidad y a las variaciones de tarifas según la temporada.</li>
                    <li>Si la reprogramación se solicita con menos de 8 días de anticipación, se aplicará una penalidad del 10% sobre el valor total de la reserva.</li>
                    <li>Si la reprogramación se solicita 24 horas antes o el mismo día de la reserva, se considerará como no show (no presentación) y no habrá derecho a devolución ni reprogramación.</li>
                    <li>Si el huésped no se presenta en la fecha de la reserva (no show), no tendrá derecho a devolución ni a reprogramación, considerándose el servicio como prestado.</li>
                    <li>Si el huésped decide no tomar la reserva al llegar al establecimiento, no habrá devolución de dinero ni derecho a reprogramación.</li>
                    <li>En caso de reservas grupales, si una o varias personas deciden no asistir, se deberá cancelar el valor total por el cual se hizo la reserva.</li>
                </ul>

                <h2 style="font-size: 12px; font-weight: 700; color: #111111; margin-top: 16px; margin-bottom: 8px;">2. Políticas de Ingreso, Uso y Convivencia</h2>
                <ul>
                    <li>El ingreso a las instalaciones implica la aceptación total de las políticas aquí establecidas.</li>
                    <li>El horario de ingreso y salida será informado al momento de la reserva y debe cumplirse estrictamente para garantizar la adecuada operación del centro vacacional.</li>
                    <li>No está permitido el ingreso de bebidas alcohólicas, mecatos ni bebidas gaseosas.</li>
                    <li>Está prohibido el ruido excesivo, el uso de parlantes a alto volumen o cualquier conducta que perturbe la tranquilidad de otros huéspedes.</li>
                    <li>Los objetos personales, documentos y artículos de valor son responsabilidad exclusiva del huésped. El establecimiento no se hace responsable por pérdida, hurto o daño de los mismos.</li>
                    <li>El huésped será responsable por daños, deterioros o pérdidas ocasionadas a los bienes del centro vacacional (muebles, lencería, electrodomésticos, instalaciones, zonas verdes, etc.), debiendo asumir el costo total de reparación o reposición.</li>
                    <li>Todos los huéspedes y visitantes deberán mantener un trato respetuoso, cordial y considerado hacia el personal del centro vacacional, así como hacia otros huéspedes y visitantes.</li>
                    <li>No se tolerarán actos de irrespeto, agresiones verbales o físicas, comportamientos discriminatorios o alteraciones del orden público. Cualquier incumplimiento podrá ser motivo de retiro inmediato sin derecho a reembolso.</li>
                    <li>El establecimiento se reserva el derecho de terminar la estadía o negar el ingreso a quienes incumplan las normas de convivencia, higiene o respeto.</li>
                </ul>

                <h2 style="font-size: 12px; font-weight: 700; color: #111111; margin-top: 16px; margin-bottom: 8px;">3. Políticas para Mascotas</h2>
                <ul>
                    <li>Las mascotas son bienvenidas bajo las siguientes condiciones:
                        <ul style="margin-top: 6px;">
                            <li>Deben permanecer bajo supervisión y control constante de su dueño.</li>
                            <li>No pueden subirse a camas, muebles ni colchones.</li>
                            <li>No está permitido dejarlas sueltas en zonas comunes ni dentro de las instalaciones.</li>
                            <li>Los propietarios deberán recoger sus desechos y mantener la limpieza del lugar.</li>
                            <li>Cualquier daño causado por la mascota será responsabilidad exclusiva del dueño.</li>
                        </ul>
                    </li>
                </ul>

                <h2 style="font-size: 12px; font-weight: 700; color: #111111; margin-top: 16px; margin-bottom: 8px;">4. Disposiciones Legales</h2>
                <p style="margin-top: 8px;">
                    Estas políticas se encuentran en concordancia con la Ley 2068 de 2020 y el Código de Comercio Colombiano (Art. 78 y siguientes), los cuales regulan la prestación de servicios turísticos en el país.
                </p>
                <p style="margin-top: 8px;">
                    El Centro Vacacional Campo Verde se reserva el derecho de admisión y permanencia de acuerdo con las normas de convivencia y seguridad establecidas.
                </p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información de Contacto</div>
            <div class="info-block">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Campo Verde:</div>
                        <div class="info-value">Centro Vacacional</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value">c.vacacionalcampoverde@gmail.com</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Teléfono:</div>
                        <div class="info-value">322 614 3787</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Dirección:</div>
                        <div class="info-value">Campo Verde - Cocorná, Antioquia</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Instrucciones de Check-in</div>
            <ul>
                <li>Presentarse en la recepción con el documento de identidad del huésped principal.</li>
                <li>Presentar este certificado de reserva (impreso o digital).</li>
                <li>Completar el registro de huéspedes si aplica.</li>
                <li>Realizar el pago pendiente si existe.</li>
                <li>Recibir las llaves y orientación sobre las instalaciones.</li>
            </ul>
        </div>

        <div class="section">
            <div class="section-title">Servicios Adicionales</div>
            <p style="margin-bottom: 10px;">Campo Verde ofrece los siguientes servicios adicionales:</p>
            <ul>
                <li>Restaurante y bar</li>
                <li>Ruta de la Panela (Programación entre semana)</li>
                <li>Zona de pesca deportiva</li>
                <li>Zona de Hamacas</li>
                <li>Actividad de Senderismo (sujeta al clima)</li>
                <li>Actividades recreativas</li>
                <li>Wi-Fi gratuito</li>
                <li>Estacionamiento</li>
            </ul>
            <p style="margin-top: 15px; font-size: 9px; font-style: italic; color: #333333;">
                Los servicios adicionales pueden tener costos adicionales. Consulte en recepción para más información.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="divider"></div>
            @if(isset($logo_base64) && $logo_base64)
                <img src="{{ $logo_base64 }}" alt="Campo Verde" class="footer-logo">
            @endif
            <div class="footer-brand">Campo Verde</div>
            <div class="footer-contact">Teléfono: 322 614 3787</div>
            <div class="footer-contact">Email: c.vacacionalcampoverde@gmail.com</div>
            <div class="footer-contact" style="margin-top: 12px;">
                <strong>Síguenos en:</strong><br>
                <a href="https://www.instagram.com/campoverdecocorna" style="color: #2F6B3F; text-decoration: none; margin-right: 12px;">Instagram</a>
                <a href="https://www.facebook.com/centrovacacional.campoverde" style="color: #2F6B3F; text-decoration: none; margin-right: 12px;">Facebook</a>
                <a href="https://www.youtube.com/@centrovacacionalcampoverde4511" style="color: #2F6B3F; text-decoration: none;">YouTube</a>
            </div>
            <div class="footer-legal">Documento generado el {{ $date }} a las {{ $time }}</div>
        </div>
    </div>
</body>
</html>
