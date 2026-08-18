<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de QRs - Inventario</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            color: #111;
        }
        .sheet {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-auto-rows: 33mm;
            gap: 4mm;
            page-break-inside: auto;
        }
        .label {
            border: 1px dashed #888;
            padding: 4px 6px;
            display: flex;
            gap: 6px;
            align-items: center;
            overflow: hidden;
        }
        .label svg {
            width: 28mm;
            height: 28mm;
            flex-shrink: 0;
        }
        .label .meta {
            font-size: 9pt;
            line-height: 1.2;
            min-width: 0;
        }
        .label .meta strong {
            display: block;
            font-size: 10pt;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .label .meta .small {
            font-size: 7.5pt;
            color: #444;
            word-break: break-all;
        }
        .sheet .label:nth-child(24n+1) { page-break-before: auto; }
        .print-hint {
            position: fixed;
            top: 8px;
            right: 8px;
            background: #111;
            color: #fff;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 11pt;
        }
        @media print {
            .print-hint { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-hint" onclick="window.print()">Imprimir</button>
    <div class="sheet">
        @foreach ($labels as $label)
            <div class="label">
                {!! $label['svg'] !!}
                <div class="meta">
                    <strong>#{{ $label['id'] }} {{ $label['name'] }}</strong>
                    @if ($label['category'])
                        <div class="small">{{ $label['category'] }}</div>
                    @endif
                    @if ($label['barcode'])
                        <div class="small">BC: {{ $label['barcode'] }}</div>
                    @endif
                    <div class="small">{{ $label['qr_code'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
    <script>
        setTimeout(function () { window.print(); }, 600);
    </script>
</body>
</html>