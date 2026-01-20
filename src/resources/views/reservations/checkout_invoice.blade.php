<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura Consolidada - {{ $invoice_number }}</title>
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
        .header-title h2 {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            margin: 4px 0 0 0;
            text-transform: uppercase;
        }
        .header-info {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
        }
        .invoice-info {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111111;
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
        /* Totales */
        .totals {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #D9D9D9;
        }
        .total-item {
            display: table;
            width: 100%;
            margin: 8px 0;
        }
        .total-label {
            display: table-cell;
            font-weight: 700;
            width: 60%;
            color: #111111;
        }
        .total-value {
            display: table-cell;
            text-align: right;
            font-weight: 700;
            color: #111111;
        }
        .grand-total {
            font-size: 16px;
            border-top: 1px solid #D9D9D9;
            padding-top: 12px;
            margin-top: 12px;
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
                <h1>Campo Verde Centro Vacacional</h1>
                <h2>FACTURA CONSOLIDADA DE CHECK-OUT</h2>
                <div class="subtext">Fecha: {{ $date }} | Hora: {{ $time }}</div>
            </div>
            <div class="header-info">
                <div class="invoice-info">
                    <strong>Número de Factura:</strong><br>
                    {{ $invoice_number }}
                </div>
            </div>
        </div>

        <!-- Información del Cliente y Reserva -->
        <div class="two-columns">
            <div class="column">
                <div class="section">
                    <div class="section-title">Información del Cliente</div>
                    <div class="info-block">
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Nombre:</div>
                                <div class="info-value">
                                    @if($customer->customer_type === 'company')
                                        {{ $customer->company_name }}
                                    @else
                                        {{ $customer->name }} {{ $customer->last_name }}
                                    @endif
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Documento:</div>
                                <div class="info-value">
                                    @if($customer->customer_type === 'company')
                                        NIT: {{ $customer->company_nit }}
                                    @else
                                        {{ $customer->dni }}
                                    @endif
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Email:</div>
                                <div class="info-value">{{ $customer->email ?? 'N/A' }}</div>
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
                    <div class="section-title">Información de la Reserva</div>
                    <div class="info-block">
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Número de Reserva:</div>
                                <div class="info-value">{{ $reservation->reservation_number }}</div>
                            </div>
                            @if($room)
                                <div class="info-row">
                                    <div class="info-label">Habitación:</div>
                                    <div class="info-value">{{ $room->number ?? 'N/A' }}</div>
                                </div>
                            @endif
                            @if($roomType)
                                <div class="info-row">
                                    <div class="info-label">Tipo de Habitación:</div>
                                    <div class="info-value">{{ $roomType->name }}</div>
                                </div>
                            @endif
                            <div class="info-row">
                                <div class="info-label">Check-in:</div>
                                <div class="info-value">{{ $reservation->check_in_date->format('d/m/Y') }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Check-out:</div>
                                <div class="info-value">{{ $reservation->check_out_time ? $reservation->check_out_time->format('d/m/Y H:i') : 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle de Reserva y Servicios -->
        @php
            $additionalTotal = $reservation->additionalServices ? $reservation->additionalServices->sum('total') : 0;
            $nights = max(1, $reservation->check_in_date->diffInDays($reservation->check_out_date ?? $reservation->check_in_date));
            $baseAlojamiento = $totals['reservation'] - $additionalTotal;
        @endphp
        <div class="section">
            <div class="section-title">Detalle de Reserva y Servicios</div>
            <table>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Precio Unitario</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Alojamiento {{ $reservation->reservation_type === 'day_pass' ? 'Pasadía' : ($roomType->name ?? 'Habitación') }} - {{ $reservation->check_in_date->format('d/m/Y') }} a {{ $reservation->check_out_date ? $reservation->check_out_date->format('d/m/Y') : $reservation->check_in_date->format('d/m/Y') }}</td>
                        <td class="text-right">{{ $reservation->adults + $reservation->children + $reservation->infants }} huésped(es)</td>
                        <td class="text-right">${{ number_format($baseAlojamiento / $nights, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($baseAlojamiento, 0, ',', '.') }}</td>
                    </tr>
                    @if($reservation->additionalServices && $reservation->additionalServices->count() > 0)
                        @foreach($reservation->additionalServices as $ras)
                            <tr>
                                <td>{{ optional($ras->additionalService)->name ?? 'N/A' }}</td>
                                <td class="text-right">{{ $ras->quantity }} {{ $ras->quantity == 1 ? 'unidad' : 'unid.' }}</td>
                                <td class="text-right">${{ number_format($ras->unit_price, 0, ',', '.') }}</td>
                                <td class="text-right">${{ number_format($ras->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Consumos del Kiosko -->
        @if($kioskInvoices->count() > 0)
        <div class="section">
            <div class="section-title">Consumos del Kiosko</div>
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Precio Unitario</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kioskInvoices as $invoice)
                        @foreach($invoice->details as $detail)
                            <tr>
                                <td>#{{ $invoice->payment_code }}</td>
                                <td>{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $detail->kiosk_unit->product->name ?? 'N/A' }}</td>
                                <td class="text-right">1</td>
                                <td class="text-right">${{ number_format($detail->price, 0, ',', '.') }}</td>
                                <td class="text-right">${{ number_format($detail->price, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Resumen de Pagos -->
        @if($payments->count() > 0)
        <div class="section">
            <div class="section-title">Resumen de Pagos</div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Método de Pago</th>
                        <th class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr>
                            <td>{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $payment->concept ?? 'Pago' }}</td>
                            <td>{{ $payment->paymentType->name ?? 'N/A' }}</td>
                            <td class="text-right">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Cargos a la Habitación -->
        @if($creditPayments->count() > 0)
        <div class="section">
            <div class="section-title">Cargos a la Habitación</div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Método de Pago</th>
                        <th class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($creditPayments as $payment)
                        <tr>
                            <td>{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $payment->concept }}</td>
                            <td>{{ $payment->paymentType->name ?? 'N/A' }}</td>
                            <td class="text-right">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Totales -->
        <div class="totals">
            <div class="total-item">
                <div class="total-label"><strong>Subtotal Reserva:</strong></div>
                <div class="total-value"><strong>${{ number_format($totals['reservation'], 0, ',', '.') }}</strong></div>
            </div>
            @if($totals['kiosko'] > 0)
            <div class="total-item">
                <div class="total-label"><strong>Subtotal Consumos Kiosko:</strong></div>
                <div class="total-value"><strong>${{ number_format($totals['kiosko'], 0, ',', '.') }}</strong></div>
            </div>
            @endif
            <div class="total-item grand-total">
                <div class="total-label">TOTAL GENERAL:</div>
                <div class="total-value">${{ number_format($totals['grand_total'], 0, ',', '.') }}</div>
            </div>
            <div class="total-item">
                <div class="total-label">Total Pagado:</div>
                <div class="total-value">${{ number_format($totals['paid'], 0, ',', '.') }}</div>
            </div>
            @if($totals['pending'] > 0)
            <div class="total-item">
                <div class="total-label" style="color: #111111;">SALDO PENDIENTE:</div>
                <div class="total-value" style="color: #111111; font-weight: 700;">${{ number_format($totals['pending'], 0, ',', '.') }}</div>
            </div>
            @else
            <div class="total-item">
                <div class="total-label" style="color: #111111;">ESTADO:</div>
                <div class="total-value" style="color: #111111; font-weight: 700;">PAGADO COMPLETAMENTE</div>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div style="border-top: 1px solid #D9D9D9; margin: 24px 0; padding-top: 16px;"></div>
            @if(isset($logo_base64) && $logo_base64)
                <img src="{{ $logo_base64 }}" alt="Campo Verde" class="footer-logo">
            @endif
            <div class="footer-brand">Campo Verde</div>
            <div class="footer-contact">Teléfono: 322 614 3787</div>
            <div class="footer-contact">Email: c.vacacionalcampoverde@gmail.com</div>
            <div class="footer-legal">Gracias por su estadía en Campo Verde Centro Vacacional</div>
            <div class="footer-legal">Este documento es una factura consolidada de todos los consumos durante su estadía.</div>
            <div class="footer-legal">Documento generado el {{ $date }} a las {{ $time }}</div>
        </div>
    </div>
</body>
</html>
