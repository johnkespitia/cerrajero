<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura Consolidada - {{ $invoice_number }}</title>
    <style>
        @page {
            margin: 15mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2d5016;
        }
        .logo-container {
            margin-bottom: 10px;
        }
        .logo-img {
            max-width: 120px;
            max-height: 60px;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .invoice-info-left, .invoice-info-right {
            width: 48%;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            background-color: #2d5016;
            color: white;
            padding: 8px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            border-top: 2px solid #2d5016;
            padding-top: 15px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .total-row.grand-total {
            font-size: 14pt;
            font-weight: bold;
            border-top: 2px solid #2d5016;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            @if($logo_base64)
                <img src="{{ $logo_base64 }}" alt="Logo" class="logo-img">
            @endif
        </div>
        <h1>Campo Verde Centro Vacacional</h1>
        <h2>FACTURA CONSOLIDADA DE CHECK-OUT</h2>
        <p><strong>Número de Factura:</strong> {{ $invoice_number }}</p>
        <p><strong>Fecha:</strong> {{ $date }} <strong>Hora:</strong> {{ $time }}</p>
    </div>

    <div class="invoice-info">
        <div class="invoice-info-left">
            <h3>Información del Cliente</h3>
            <p><strong>Nombre:</strong> 
                @if($customer->customer_type === 'company')
                    {{ $customer->company_name }}
                @else
                    {{ $customer->name }} {{ $customer->last_name }}
                @endif
            </p>
            <p><strong>Documento:</strong> 
                @if($customer->customer_type === 'company')
                    NIT: {{ $customer->company_nit }}
                @else
                    {{ $customer->dni }}
                @endif
            </p>
            <p><strong>Email:</strong> {{ $customer->email ?? 'N/A' }}</p>
            <p><strong>Teléfono:</strong> {{ $customer->phone_number ?? 'N/A' }}</p>
        </div>
        <div class="invoice-info-right">
            <h3>Información de la Reserva</h3>
            <p><strong>Número de Reserva:</strong> {{ $reservation->reservation_number }}</p>
            <p><strong>Habitación:</strong> {{ $room->number ?? 'N/A' }}</p>
            <p><strong>Tipo de Habitación:</strong> {{ $roomType->name ?? 'N/A' }}</p>
            <p><strong>Check-in:</strong> {{ $reservation->check_in_date->format('d/m/Y') }}</p>
            <p><strong>Check-out:</strong> {{ $reservation->check_out_time ? $reservation->check_out_time->format('d/m/Y H:i') : 'N/A' }}</p>
        </div>
    </div>

    <!-- Detalle de Reserva -->
    <div class="section">
        <div class="section-title">DETALLE DE RESERVA Y SERVICIOS</div>
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
                    <td>Habitación {{ $roomType->name ?? 'N/A' }} - {{ $reservation->check_in_date->format('d/m/Y') }} a {{ $reservation->check_out_date ? $reservation->check_out_date->format('d/m/Y') : 'N/A' }}</td>
                    <td class="text-right">{{ $reservation->adults + $reservation->children + $reservation->infants }} huésped(es)</td>
                    <td class="text-right">${{ number_format($totals['reservation'] / max(1, $reservation->check_in_date->diffInDays($reservation->check_out_date ?? $reservation->check_in_date)), 2) }}</td>
                    <td class="text-right">${{ number_format($totals['reservation'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Consumos del Kiosko -->
    @if($kioskInvoices->count() > 0)
    <div class="section">
        <div class="section-title">CONSUMOS DEL KIOSKO</div>
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
                            <td class="text-right">${{ number_format($detail->price, 2) }}</td>
                            <td class="text-right">${{ number_format($detail->price, 2) }}</td>
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
        <div class="section-title">RESUMEN DE PAGOS</div>
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
                        <td class="text-right">${{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Cargos a la Habitación -->
    @if($creditPayments->count() > 0)
    <div class="section">
        <div class="section-title">CARGOS A LA HABITACIÓN</div>
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
                        <td class="text-right">${{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Totales -->
    <div class="totals">
        <div class="total-row">
            <span><strong>Subtotal Reserva:</strong></span>
            <span><strong>${{ number_format($totals['reservation'], 2) }}</strong></span>
        </div>
        @if($totals['kiosko'] > 0)
        <div class="total-row">
            <span><strong>Subtotal Consumos Kiosko:</strong></span>
            <span><strong>${{ number_format($totals['kiosko'], 2) }}</strong></span>
        </div>
        @endif
        <div class="total-row grand-total">
            <span>TOTAL GENERAL:</span>
            <span>${{ number_format($totals['grand_total'], 2) }}</span>
        </div>
        <div class="total-row">
            <span>Total Pagado:</span>
            <span>${{ number_format($totals['paid'], 2) }}</span>
        </div>
        @if($totals['pending'] > 0)
        <div class="total-row" style="color: #dc3545; font-weight: bold;">
            <span>SALDO PENDIENTE:</span>
            <span>${{ number_format($totals['pending'], 2) }}</span>
        </div>
        @else
        <div class="total-row" style="color: #28a745; font-weight: bold;">
            <span>ESTADO:</span>
            <span>PAGADO COMPLETAMENTE</span>
        </div>
        @endif
    </div>

    <div class="footer">
        <p>Gracias por su estadía en Campo Verde Centro Vacacional</p>
        <p>Este documento es una factura consolidada de todos los consumos durante su estadía.</p>
        <p>Para consultas o reclamos, por favor contacte a recepción.</p>
    </div>
</body>
</html>

