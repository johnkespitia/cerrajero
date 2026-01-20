<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'Campo Verde Centro Vacacional')</title>
    <style>
        /* Reset y estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #111111;
            background-color: #F6F6F6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 0;
            margin: 0;
        }
        
        /* Contenedor principal */
        .email-wrapper {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            background-color: #FFFFFF;
            padding: 24px;
        }
        
        /* Header con logo y título */
        .email-header {
            margin-bottom: 32px;
        }
        
        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 24px;
        }
        
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 30%;
        }
        
        .logo {
            max-width: 100px;
            max-height: 100px;
            width: auto;
            height: auto;
            display: block;
        }
        
        .header-title {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 40%;
        }
        
        .header-title h1 {
            font-family: Georgia, "Times New Roman", Times, serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #111111;
            margin: 0;
            line-height: 1.2;
        }
        
        .header-title .subtitle {
            font-family: Georgia, "Times New Roman", Times, serif;
            font-size: 18px;
            font-weight: 400;
            color: #333333;
            margin-top: 4px;
        }
        
        .header-reservation {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
        }
        
        .reservation-number {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            text-transform: uppercase;
        }
        
        /* Contenido principal */
        .email-content {
            color: #111111;
        }
        
        /* Tipografía */
        h1 {
            font-family: Georgia, "Times New Roman", Times, serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #111111;
            margin: 0 0 16px 0;
        }
        
        h2 {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            color: #111111;
            margin: 24px 0 12px 0;
            letter-spacing: 0.5px;
        }
        
        h3 {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            margin: 20px 0 10px 0;
        }
        
        p {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333333;
            margin: 12px 0;
        }
        
        strong {
            font-weight: 700;
            color: #111111;
        }
        
        /* Bloques de información */
        .info-block {
            margin: 24px 0;
        }
        
        .info-block-two-columns {
            display: table;
            width: 100%;
            margin: 24px 0;
        }
        
        .info-block-left {
            display: table-cell;
            vertical-align: top;
            width: 40%;
            padding-right: 16px;
        }
        
        .info-block-right {
            display: table-cell;
            vertical-align: top;
            width: 60%;
        }
        
        .info-label {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #333333;
            margin: 8px 0 4px 0;
            text-transform: uppercase;
        }
        
        .info-value {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            margin: 4px 0 8px 0;
        }
        
        /* Tablas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
        }
        
        table th {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #111111;
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid #D9D9D9;
            text-transform: uppercase;
        }
        
        table td {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            color: #111111;
            padding: 12px 8px;
            border-bottom: 1px solid #D9D9D9;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .total-row {
            font-weight: 700;
            background-color: #F9F9F9;
        }
        
        .total-row td {
            color: #111111;
            font-size: 16px;
        }
        
        /* Resumen de pagos */
        .payment-summary {
            margin: 24px 0;
        }
        
        .payment-summary-item {
            display: table;
            width: 100%;
            margin: 12px 0;
        }
        
        .payment-summary-label {
            display: table-cell;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            text-transform: uppercase;
            width: 60%;
        }
        
        .payment-summary-value {
            display: table-cell;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #111111;
            text-align: right;
            width: 40%;
        }
        
        /* Divisores */
        .divider {
            border-top: 1px solid #D9D9D9;
            margin: 24px 0;
        }
        
        /* Sección de dos columnas (Horarios/Políticas) */
        .two-columns-section {
            display: table;
            width: 100%;
            margin: 32px 0;
        }
        
        .column-left {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            padding-right: 16px;
        }
        
        .column-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            padding-left: 16px;
        }
        
        /* Listas */
        ul, ol {
            margin: 16px 0;
            padding-left: 24px;
        }
        
        li {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            color: #333333;
            margin: 8px 0;
            line-height: 1.6;
        }
        
        /* Footer */
        .email-footer {
            margin-top: 32px;
            padding-top: 24px;
        }
        
        .footer-thanks {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        
        .footer-thanks h2 {
            font-family: Georgia, "Times New Roman", Times, serif;
            font-size: 24px;
            font-weight: 700;
            color: #111111;
            margin: 0;
            text-transform: none;
        }
        
        .footer-brand {
            text-align: center;
            margin: 24px 0;
        }
        
        .footer-logo-small {
            max-width: 56px;
            max-height: 56px;
            width: auto;
            height: auto;
            margin: 0 auto 12px;
            display: block;
        }
        
        .footer-brand-name {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 12px;
        }
        
        .footer-contact {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #333333;
            margin: 8px 0;
        }
        
        .footer-contact a {
            color: #2F6B3F;
            text-decoration: none;
        }
        
        .footer-contact a:hover {
            text-decoration: underline;
        }
        
        .footer-legal {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #666666;
            line-height: 1.5;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #D9D9D9;
        }
        
        /* Enlaces */
        a {
            color: #2F6B3F;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 16px !important;
            }
            
            .header-top {
                display: block;
            }
            
            .header-logo,
            .header-title,
            .header-reservation {
                display: block;
                width: 100%;
                text-align: center;
                margin-bottom: 16px;
            }
            
            .header-title h1 {
                font-size: 24px;
            }
            
            .info-block-two-columns,
            .two-columns-section {
                display: block;
            }
            
            .info-block-left,
            .info-block-right,
            .column-left,
            .column-right {
                display: block;
                width: 100%;
                padding: 0;
                margin-bottom: 16px;
            }
            
            table {
                font-size: 12px;
            }
            
            table th,
            table td {
                padding: 8px 4px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            h2 {
                font-size: 14px;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="email-wrapper">
        <!-- Header con Logo, Título y Número de Reserva -->
        <div class="email-header">
            <div class="header-top">
                <div class="header-logo">
                    @if(isset($logoUrl) && $logoUrl)
                        <img src="{{ $logoUrl }}" alt="Campo Verde" class="logo">
                    @else
                        <div style="font-family: Georgia, serif; font-size: 16px; color: #2F6B3F; font-weight: 700;">Campo Verde</div>
                    @endif
                </div>
                <div class="header-title">
                    @yield('header-title', '<h1>CONFIRMACIÓN</h1><div class="subtitle">CAMPO VERDE</div>')
                </div>
                <div class="header-reservation">
                    @yield('reservation-number', '')
                </div>
            </div>
        </div>
        
        <!-- Contenido Principal -->
        <div class="email-content">
            @yield('content')
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <!-- Bloque 1: Cierre humano -->
            <div class="footer-thanks">
                <h2>¡Muchas gracias!</h2>
            </div>
            
            <div class="divider"></div>
            
            <!-- Bloque 2: Marca + Contacto -->
            <div class="footer-brand">
                @if(isset($logoUrl) && $logoUrl)
                    <img src="{{ $logoUrl }}" alt="Campo Verde" class="footer-logo-small">
                @endif
                <div class="footer-brand-name">Campo Verde Centro Vacacional</div>
                
                <div class="footer-contact">
                    <strong>📍 Dirección:</strong><br>
                    Campo Verde - Cocorná, Antioquia
                </div>
                
                <div class="footer-contact">
                    <strong>📧 Email:</strong><br>
                    <a href="mailto:c.vacacionalcampoverde@gmail.com">c.vacacionalcampoverde@gmail.com</a>
                </div>
                
                <div class="footer-contact">
                    <strong>📞 Teléfono:</strong><br>
                    <a href="tel:+573226143787">322 614 3787</a>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <!-- Bloque 3: Legal -->
            <div class="footer-legal">
                <p>Este correo es una confirmación automática. Por favor, no responda a este mensaje.</p>
                <p>Para más información sobre nuestros servicios o políticas de cancelación, contáctenos.</p>
                <p style="margin-top: 12px;">&copy; {{ date('Y') }} Campo Verde Centro Vacacional. Todos los derechos reservados.</p>
            </div>
        </div>
    </div>
</body>
</html>
