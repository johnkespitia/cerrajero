@extends('emails.layouts.base')

@section('title', 'Confirmación de Pago - Reserva #' . $reservation->reservation_number)

@section('header-title')
    <h1>CONFIRMACIÓN PAGO</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #{{ $reservation->reservation_number }}</div>
@endsection

@section('content')
    <p>Estimado/a 
        @if($customer->customer_type === 'company')
            {{ $customer->company_name }}
        @else
            {{ $customer->name }} {{ $customer->last_name }}
        @endif,
    </p>
    
    <p>Le informamos que se ha registrado un pago para su reserva. A continuación encontrará el detalle completo:</p>
    
    <!-- Información de la Reserva -->
    <div class="info-block">
        <h3>Información de la Reserva:</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">NÚMERO DE RESERVA:</div>
                <div class="info-label">FECHA DE CHECK-IN:</div>
                @if($reservation->check_out_date)
                    <div class="info-label">FECHA DE CHECK-OUT:</div>
                @endif
                @if($reservation->room)
                    <div class="info-label">HABITACIÓN:</div>
                @endif
                <div class="info-label">HUÉSPEDES:</div>
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $reservation->reservation_number }}</div>
                <div class="info-value">{{ $reservation->check_in_date->format('d/m/Y') }}</div>
                @if($reservation->check_out_date)
                    <div class="info-value">{{ $reservation->check_out_date->format('d/m/Y') }}</div>
                @endif
                @if($reservation->room)
                    <div class="info-value">{{ $reservation->room->display_name }}</div>
                @endif
                <div class="info-value">{{ $reservation->adults }} adultos, {{ $reservation->children }} niños, {{ $reservation->infants }} bebés</div>
            </div>
        </div>
    </div>

    <!-- Servicios Adicionales -->
    @if($reservation->additionalServices && $reservation->additionalServices->count() > 0)
    <div class="info-block">
        <h2>Servicios Adicionales</h2>
        <table>
            <thead>
                <tr>
                    <th class="text-left">SERVICIO</th>
                    <th class="text-center">CANTIDAD</th>
                    <th class="text-right">PRECIO UNITARIO</th>
                    <th class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reservation->additionalServices as $service)
                    <tr>
                        <td class="text-left">{{ $service->additionalService->name ?? 'N/A' }}</td>
                        <td class="text-center">{{ number_format($service->quantity, 2) }}</td>
                        <td class="text-right">${{ number_format($service->unit_price, 2) }}</td>
                        <td class="text-right">${{ number_format($service->total, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>Subtotal Servicios Adicionales:</strong></td>
                    <td class="text-right"><strong>${{ number_format($reservation->additionalServices->sum('total'), 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Compras en el Kiosko Pendientes de Pago -->
    @if($pendingKioskInvoices && $pendingKioskInvoices->count() > 0)
    <div class="info-block">
        <h2>Compras en el Kiosko Pendientes de Pago</h2>
        <table>
            <thead>
                <tr>
                    <th class="text-left">FACTURA</th>
                    <th class="text-left">FECHA</th>
                    <th class="text-left">PRODUCTO</th>
                    <th class="text-right">PRECIO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingKioskInvoices as $invoice)
                    @foreach($invoice->details as $detail)
                        <tr>
                            <td class="text-left">#{{ $invoice->payment_code }}</td>
                            <td class="text-left">{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-left">{{ $detail->kiosk_unit->product->name ?? 'N/A' }}</td>
                            <td class="text-right">${{ number_format($detail->price, 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>Subtotal Kiosko Pendiente:</strong></td>
                    <td class="text-right"><strong>${{ number_format($pendingKioskInvoices->sum(function($inv) { return $inv->details->sum('price'); }), 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Resumen de Pagos -->
    <div class="info-block">
        <h2>Pago Registrado</h2>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">MONTO:</div>
                <div class="info-label">MÉTODO DE PAGO:</div>
                @if($payment->payment_reference)
                    <div class="info-label">REFERENCIA:</div>
                @endif
                @if($payment->concept)
                    <div class="info-label">CONCEPTO:</div>
                @endif
                <div class="info-label">FECHA:</div>
            </div>
            <div class="info-block-right">
                <div class="info-value">${{ number_format($payment->amount, 2) }}</div>
                <div class="info-value">{{ $payment->paymentType->name ?? 'N/A' }}</div>
                @if($payment->payment_reference)
                    <div class="info-value">{{ $payment->payment_reference }}</div>
                @endif
                @if($payment->concept)
                    <div class="info-value">{{ $payment->concept }}</div>
                @endif
                <div class="info-value">{{ $payment->created_at->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    <!-- Resumen Financiero -->
    <div class="info-block">
        <h2>Resumen Financiero</h2>
        <div class="payment-summary">
            <div class="payment-summary-item">
                <div class="payment-summary-label">TOTAL DE LA RESERVA (incluye servicios adicionales):</div>
                <div class="payment-summary-value">${{ number_format($reservation->final_price ?? $reservation->total_price, 2) }}</div>
            </div>
            @if($pendingKioskInvoices && $pendingKioskInvoices->count() > 0)
            <div class="payment-summary-item">
                <div class="payment-summary-label">COMPRAS KIOSKO PENDIENTES:</div>
                <div class="payment-summary-value">${{ number_format($pendingKioskInvoices->sum(function($inv) { return $inv->details->sum('price'); }), 2) }}</div>
            </div>
            @endif
            <div class="payment-summary-item total-row" style="background-color: #F9F9F9; padding: 12px 0;">
                <div class="payment-summary-label">TOTAL GENERAL:</div>
                <div class="payment-summary-value">${{ number_format($totalDue, 2) }}</div>
            </div>
            <div class="payment-summary-item">
                <div class="payment-summary-label">TOTAL PAGOS REGISTRADOS:</div>
                <div class="payment-summary-value">${{ number_format($totalPaid, 2) }}</div>
            </div>
            <div class="payment-summary-item total-row" style="background-color: #F9F9F9; padding: 12px 0;">
                <div class="payment-summary-label">NUEVO SALDO PENDIENTE:</div>
                <div class="payment-summary-value">${{ number_format($newBalance, 2) }}</div>
            </div>
        </div>
    </div>
    
    <p>Si tiene alguna pregunta o necesita asistencia adicional, no dude en contactarnos.</p>
    
    <p>¡Gracias por elegir Campo Verde!</p>
@endsection
