@extends('emails.layouts.base')

@section('title', 'Confirmación de Pago - Reserva #341')

@section('header-title')
    <h1>CONFIRMACIÓN PAGO</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('reservation-number')
    <div class="reservation-number">RESERVA #341</div>
@endsection

@section('content')
    <!-- Bloque Cliente / Fechas -->
    <div class="info-block-two-columns">
        <div class="info-block-left">
            <div class="info-label">CLIENTE:</div>
            <div class="info-label">CC:</div>
            <div class="info-label">CEL:</div>
            <div class="info-label">FECHA DE LLEGADA:</div>
            <div class="info-label">FECHA DE SALIDA:</div>
        </div>
        <div class="info-block-right">
            <div class="info-value">DANIEL AREIZA</div>
            <div class="info-value">1088296366</div>
            <div class="info-value">3217925634</div>
            <div class="info-value">18 ENERO</div>
            <div class="info-value">19 ENERO</div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Tabla de Ítems (Plan) -->
    <h2>Plan de Reserva</h2>
    <table>
        <thead>
            <tr>
                <th class="text-left">PLAN</th>
                <th class="text-center">noche</th>
                <th class="text-center">Cantidad Personas</th>
                <th class="text-right">Precio/u</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-left">HABITACION SIN JACUZZY PLAN #3 (CENA DESAYUNO ALMUERZO)</td>
                <td class="text-center">1</td>
                <td class="text-center">2</td>
                <td class="text-right">$210.000</td>
                <td class="text-right">$420.000</td>
            </tr>
            <tr>
                <td class="text-left">niño adicional (cena dessyuno almurerzo)</td>
                <td class="text-center">1</td>
                <td class="text-center">1</td>
                <td class="text-right">$195.000</td>
                <td class="text-right">$195.000</td>
            </tr>
        </tbody>
    </table>

    <!-- Resumen de Pagos (Totales) -->
    <div class="payment-summary">
        <div class="payment-summary-item">
            <div class="payment-summary-label">TOTAL RESERVA</div>
            <div class="payment-summary-value">$615.000</div>
        </div>
        <div class="payment-summary-item">
            <div class="payment-summary-label">ABONÓ</div>
            <div class="payment-summary-value">$115.000</div>
        </div>
        <div class="payment-summary-item total-row" style="background-color: #F9F9F9; padding: 12px 0;">
            <div class="payment-summary-label">RESTA</div>
            <div class="payment-summary-value">$500.000</div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Sección Información de Pago -->
    <h2>Información de Pago</h2>
    <div class="info-block-two-columns">
        <div class="info-block-left">
            <div class="info-label">NOMBRE TITULAR:</div>
            <div class="info-label">CC:</div>
            <div class="info-label">BANCO / TIPO:</div>
            <div class="info-label">CUENTA:</div>
        </div>
        <div class="info-block-right">
            <div class="info-value">DANIEL AREIZA</div>
            <div class="info-value">1088296366</div>
            <div class="info-value">Bancolombia / Ahorros</div>
            <div class="info-value">1234567890</div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Sección Horarios / Políticas (2 columnas) -->
    <div class="two-columns-section">
        <div class="column-left">
            <h2>HORARIOS HABITACIONES Y CABAÑAS</h2>
            <div class="info-block">
                <p><strong>CHECK-IN / INGRESO</strong></p>
                <p>Desde 9:00 am (Instalaciones)</p>
                <p>Entrega habitación 3:00 pm</p>
            </div>
            <div class="info-block">
                <p><strong>FECHA DE LLEGADA:</strong> 18 ENERO</p>
                <p><strong>FECHA DE SALIDA:</strong> 19 ENERO</p>
            </div>
            <div class="payment-summary" style="margin-top: 16px;">
                <div class="payment-summary-item">
                    <div class="payment-summary-label">TOTAL RESERVA</div>
                    <div class="payment-summary-value">$615.000</div>
                </div>
                <div class="payment-summary-item">
                    <div class="payment-summary-label">ABONÓ</div>
                    <div class="payment-summary-value">$115.000</div>
                </div>
                <div class="payment-summary-item">
                    <div class="payment-summary-label">RESTA</div>
                    <div class="payment-summary-value">$500.000</div>
                </div>
            </div>
        </div>
        <div class="column-right">
            <h2>Recordatorios y Políticas</h2>
            <ul>
                <li>☀️ Recuerda traer protector solar.</li>
                <li>🦟 Recuerda traer repelente para mosquitos y zancudos.</li>
                <li>🆔 Recuerda traer tu documento de identificación.</li>
                <li>🐾 Somos PET FRIENDLY.</li>
                <li>🌿 Evitemos botar basura.</li>
                <li>🚫 No está permitido el ingreso de LICOR, BEBIDAS Y MECATO.</li>
                <li>🚌 Ten en cuenta que si vas en transporte público, el último bus sale a las 5:00 pm.</li>
            </ul>
            <div class="info-block" style="margin-top: 16px;">
                <p><strong>☀️ Horario del DÍA DE SOL.</strong></p>
                <p>Ingreso 9:00 am - Salida 5:00 pm</p>
            </div>
        </div>
    </div>
@endsection
