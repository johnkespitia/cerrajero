<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificado de Check-out - {{ $reservation->reservation_number }}</title>
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
    </style>
</head>
<body>
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
                <h1>CERTIFICADO DE CHECK-OUT</h1>
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
                                @if($roomType)
                                    <div class="info-row">
                                        <div class="info-label">Tipo de Habitación:</div>
                                        <div class="info-value">{{ $roomType->name }}</div>
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
                                    <div class="info-value">
                                        {{ $reservation->check_in_date->format('d/m/Y') }}
                                        @if($reservation->check_in_time)
                                            {{ \Carbon\Carbon::parse($reservation->check_in_time)->format('H:i') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Check-out:</div>
                                    <div class="info-value">
                                        {{ $reservation->check_out_date ? $reservation->check_out_date->format('d/m/Y') : 'N/A' }}
                                        @if($reservation->check_out_time)
                                            {{ \Carbon\Carbon::parse($reservation->check_out_time)->format('H:i') }}
                                        @endif
                                    </div>
                                </div>
                                @php
                                    $checkIn = \Carbon\Carbon::parse($reservation->check_in_date);
                                    $checkOut = $reservation->check_out_date ? \Carbon\Carbon::parse($reservation->check_out_date) : $checkIn;
                                    $nights = $checkIn->diffInDays($checkOut);
                                @endphp
                                <div class="info-row">
                                    <div class="info-label">Noches:</div>
                                    <div class="info-value">{{ $nights }}</div>
                                </div>
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

        @if($guests && $guests->count() > 0)
            <div class="section">
                <div class="section-title">Visitantes Registrados</div>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Tipo Documento</th>
                            <th>Número Documento</th>
                            <th>EPS/Aseguradora</th>
                            <th>Tipo Seguro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($guests as $guest)
                            <tr>
                                <td>
                                    <strong>{{ $guest->first_name }} {{ $guest->last_name }}</strong>
                                    @if($guest->is_primary_guest)
                                        <span style="color: #2F6B3F; font-size: 9px;">(Principal)</span>
                                    @endif
                                    @if($guest->birth_date)
                                        <br><small style="color: #666;">Nacimiento: {{ \Carbon\Carbon::parse($guest->birth_date)->format('d/m/Y') }}</small>
                                    @endif
                                </td>
                                <td>{{ $guest->document_type ?? 'N/A' }}</td>
                                <td>{{ $guest->document_number ?? 'N/A' }}</td>
                                <td>{{ $guest->health_insurance_name ?? 'N/A' }}</td>
                                <td>
                                    @if($guest->health_insurance_type)
                                        {{ $guest->health_insurance_type === 'national' ? 'Nacional' : 'Internacional' }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                            </tr>
                            @if($guest->special_needs)
                                <tr>
                                    <td colspan="5" style="font-size: 9px; background-color: #F9F9F9; padding-left: 15px;">
                                        <strong>Necesidades Especiales:</strong> {{ $guest->special_needs }}
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

        @if($minibarCharges && $minibarCharges->count() > 0)
            <div class="section">
                <div class="section-title">Consumo de Minibar</div>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-right">Precio Unitario</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($minibarCharges as $charge)
                            <tr>
                                <td>{{ optional($charge->product)->name ?? 'N/A' }}</td>
                                <td class="text-center">{{ $charge->quantity }}</td>
                                <td class="text-right">${{ number_format($charge->unit_price, 0, ',', '.') }}</td>
                                <td class="text-right">${{ number_format($charge->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" class="text-right"><strong>Subtotal Minibar:</strong></td>
                            <td class="text-right"><strong>${{ number_format($minibarCharges->sum('total'), 0, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <div class="section">
            <div class="section-title">Resumen de Pagos</div>
            @php
                $totalPrice = $reservation->final_price ?? $reservation->total_price;
                $totalPaid = $payments ? $payments->sum('amount') : 0;
                $remainingBalance = max(0, $totalPrice - $totalPaid);
            @endphp
            
            @if($payments && $payments->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $payment->concept ?? 'Pago' }}</td>
                                <td>
                                    @if($payment->paymentType)
                                        {{ $payment->paymentType->name }}
                                    @elseif($payment->payment_method === 'cash')
                                        Efectivo
                                    @elseif($payment->payment_method === 'card')
                                        Tarjeta
                                    @elseif($payment->payment_method === 'transfer')
                                        Transferencia
                                    @elseif($payment->payment_method === 'check')
                                        Cheque
                                    @else
                                        Otro
                                    @endif
                                </td>
                                <td>{{ $payment->payment_reference ?? '-' }}</td>
                                <td class="text-right">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="4" class="text-right"><strong>Total Pagado:</strong></td>
                            <td class="text-right"><strong>${{ number_format($totalPaid, 0, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <div class="info-block" style="background-color: #F9F9F9;">
                    No se registraron pagos para esta reserva.
                </div>
            @endif

            <div class="summary-box">
                <div class="summary-item">
                    <div class="summary-label">Precio Total de la Reserva (incluye servicios adicionales y minibar):</div>
                    <div class="summary-value">${{ number_format($totalPrice, 0, ',', '.') }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Pagado:</div>
                    <div class="summary-value">${{ number_format($totalPaid, 0, ',', '.') }}</div>
                </div>
                @if($remainingBalance > 0)
                    <div class="summary-item">
                        <div class="summary-label">Saldo Pendiente:</div>
                        <div class="summary-value" style="color: #111111;">${{ number_format($remainingBalance, 0, ',', '.') }}</div>
                    </div>
                @else
                    <div class="summary-item">
                        <div class="summary-label">Estado:</div>
                        <div class="summary-value" style="color: #111111;">✓ Reserva Completamente Pagada</div>
                    </div>
                @endif
            </div>
        </div>

        @if($creditPayments && $creditPayments->count() > 0)
        <div class="section">
            <div class="section-title">Cargos a la Habitación</div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($creditPayments as $payment)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $payment->concept }}</td>
                            <td>
                                @if($payment->paymentType)
                                    {{ $payment->paymentType->name }}
                                @elseif($payment->payment_method === 'cash')
                                    Efectivo
                                @elseif($payment->payment_method === 'card')
                                    Tarjeta
                                @elseif($payment->payment_method === 'transfer')
                                    Transferencia
                                @elseif($payment->payment_method === 'check')
                                    Cheque
                                @else
                                    Otro
                                @endif
                            </td>
                            <td>{{ $payment->payment_reference ?? '-' }}</td>
                            <td class="text-right">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4" class="text-right"><strong>Total Cargos a la Habitación:</strong></td>
                        <td class="text-right"><strong>${{ number_format($creditPayments->sum('amount'), 0, ',', '.') }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

        @if($reservation->special_requests)
            <div class="section">
                <div class="section-title">Solicitudes Especiales</div>
                <div class="info-block">
                    {{ $reservation->special_requests }}
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
</body>
</html>
