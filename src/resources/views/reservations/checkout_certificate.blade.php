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
        .summary-box {
            background-color: #f0f7f0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2d5016;
            margin-top: 15px;
        }
        .summary-box strong {
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
            <div class="title">CERTIFICADO DE CHECK-OUT</div>
        </div>

        <div class="reservation-info">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Número de Reserva:</div>
                    <div class="info-value"><strong>{{ $reservation->reservation_number }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Fecha de Check-out:</div>
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
                                        <span style="color: #2d5016; font-size: 9pt;">(Principal)</span>
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
                                    <td colspan="5" style="font-size: 9pt; background-color: #fff3cd; padding-left: 15px;">
                                        <strong>Necesidades Especiales:</strong> {{ $guest->special_needs }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
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
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $payment->concept ?? 'Pago' }}</td>
                                <td>
                                    @if($payment->payment_method === 'cash')
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
                                <td style="text-align: right;">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f0f7f0; font-weight: bold;">
                            <td colspan="4" style="text-align: right;">Total Pagado:</td>
                            <td style="text-align: right;">${{ number_format($totalPaid, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <p style="padding: 15px; background-color: #fff3cd; border-radius: 5px;">
                    No se registraron pagos para esta reserva.
                </p>
            @endif

            <div class="summary-box">
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Precio Total de la Reserva:</div>
                        <div class="info-value price">${{ number_format($totalPrice, 0, ',', '.') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Total Pagado:</div>
                        <div class="info-value price">${{ number_format($totalPaid, 0, ',', '.') }}</div>
                    </div>
                    @if($remainingBalance > 0)
                        <div class="info-row">
                            <div class="info-label">Saldo Pendiente:</div>
                            <div class="info-value" style="color: #dc3545; font-weight: bold;">${{ number_format($remainingBalance, 0, ',', '.') }}</div>
                        </div>
                    @else
                        <div class="info-row">
                            <div class="info-label">Estado:</div>
                            <div class="info-value" style="color: #28a745; font-weight: bold;">✓ Reserva Completamente Pagada</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($reservation->special_requests)
            <div class="section">
                <div class="section-title">Solicitudes Especiales</div>
                <div style="background-color: #f9f9f9; padding: 12px; border-radius: 5px; border-left: 4px solid #2d5016;">
                    {{ $reservation->special_requests }}
                </div>
            </div>
        @endif

        <div class="footer">
            <p style="margin-bottom: 10px;"><strong>Gracias por su estadía en Campo Verde</strong></p>
            <p style="font-size: 9pt;">Esperamos haber superado sus expectativas y tener el placer de recibirlos nuevamente.</p>
            <p style="font-size: 9pt; margin-top: 10px;">Para consultas, contacte a: {{ config('mail.from.address', 'reservas@campoverde.com') }}</p>
            <p style="font-size: 9pt; margin-top: 5px;">Documento generado el {{ $date }} a las {{ $time }}</p>
        </div>
    </div>
</body>
</html>



