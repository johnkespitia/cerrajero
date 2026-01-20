@extends('emails.layouts.base')

@section('title', 'Confirmación de Pago - Reserva #RES202501000001')

@section('content')
    <p>Estimado/a 
        @if(isset($customer) && $customer->customer_type === 'company')
            {{ $customer->company_name }}
        @else
            {{ isset($customer) ? ($customer->name . ' ' . $customer->last_name) : 'Cliente' }}
        @endif,
    </p>
    
    <p>Le informamos que se ha registrado un pago para su reserva. A continuación encontrará el detalle completo:</p>
    
    <!-- Información de la Reserva -->
    <div class="info-box">
        <h3>Información de la Reserva:</h3>
        <p><strong>Número de Reserva:</strong> RES202501000001</p>
        <p><strong>Fecha de Check-in:</strong> 15/01/2025</p>
        <p><strong>Fecha de Check-out:</strong> 18/01/2025</p>
        <p><strong>Habitación:</strong> Cabaña 101</p>
        <p><strong>Huéspedes:</strong> 2 adultos, 1 niño, 0 bebés</p>
    </div>

    <!-- Servicios Adicionales -->
    <div class="info-box">
        <h3>Servicios Adicionales:</h3>
        <table>
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unitario</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Desayuno</td>
                    <td class="text-right">3.00</td>
                    <td class="text-right">$25,000.00</td>
                    <td class="text-right">$75,000.00</td>
                </tr>
                <tr>
                    <td>Almuerzo</td>
                    <td class="text-right">3.00</td>
                    <td class="text-right">$35,000.00</td>
                    <td class="text-right">$105,000.00</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3"><strong>Subtotal Servicios Adicionales:</strong></td>
                    <td class="text-right"><strong>$180,000.00</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Compras en el Kiosko Pendientes de Pago -->
    <div class="info-box">
        <h3>Compras en el Kiosko Pendientes de Pago:</h3>
        <table>
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th class="text-right">Precio</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>#INV-001</td>
                    <td>16/01/2025 14:30</td>
                    <td>Gaseosa</td>
                    <td class="text-right">$5,000.00</td>
                </tr>
                <tr>
                    <td>#INV-001</td>
                    <td>16/01/2025 14:30</td>
                    <td>Snacks</td>
                    <td class="text-right">$8,000.00</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3"><strong>Subtotal Kiosko Pendiente:</strong></td>
                    <td class="text-right"><strong>$13,000.00</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Resumen de Pagos -->
    <div class="payment-box">
        <h3>Pago Registrado:</h3>
        <p><strong>Monto:</strong> $500,000.00</p>
        <p><strong>Método de Pago:</strong> Efectivo</p>
        <p><strong>Referencia:</strong> REF-20250116-001</p>
        <p><strong>Concepto:</strong> Pago parcial de reserva</p>
        <p><strong>Fecha:</strong> 16/01/2025 10:30</p>
    </div>

    <!-- Resumen Financiero -->
    <div class="summary-box">
        <h3>Resumen Financiero:</h3>
        <table>
            <tbody>
                <tr>
                    <td><strong>Total de la Reserva (incluye servicios adicionales):</strong></td>
                    <td class="text-right">$1,200,000.00</td>
                </tr>
                <tr>
                    <td>Compras Kiosko Pendientes:</td>
                    <td class="text-right">$13,000.00</td>
                </tr>
                <tr class="total-row">
                    <td><strong>Total General:</strong></td>
                    <td class="text-right"><strong>$1,213,000.00</strong></td>
                </tr>
                <tr>
                    <td><strong>Total Pagos Registrados:</strong></td>
                    <td class="text-right"><strong>$500,000.00</strong></td>
                </tr>
                <tr class="total-row" style="background-color: #ffebee;">
                    <td><strong>Nuevo Saldo Pendiente:</strong></td>
                    <td class="text-right"><strong>$713,000.00</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <p>Si tiene alguna pregunta o necesita asistencia adicional, no dude en contactarnos.</p>
    
    <p>¡Gracias por elegir Campo Verde!</p>
@endsection
