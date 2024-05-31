<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta de cobro</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .signature-block {
            margin-top: 20px;
            text-align: left;
        }

        .signature-line {
            border-bottom: 1px solid #ccc;
            width: 300px;
            margin-bottom: 10px;
            margin-top: 10px;
        }

        .signature-name {
            font-weight: bold;
        }
    </style>
</head>
<body>

    <h1 style="text-align:center">CUENTA DE COBRO</h1>

    <table border="0" style="width: 100%; border-collapse: collapse;">
        <tr>
            <th style="text-align:center" colspan="2">Emisor</th>
        </tr>
        <tr>
            <th style="width: 50%;">Número</th>
            <th style="width: 50%;">Fecha de Emisión</th>
        </tr>
        <tr>
            <td>{{ $data->id }}</td>
            <td>{{ $data->generation_time }}</td>
        </tr>
        <tr>
            <th style="width: 50%;" >Razon Social</th>
            <th style="width: 50%;" colspan="2">Servicio/Objeto</th>
        </tr>
        <tr>
            <td style="width: 50%;" >
                {{ $data->professor->user->name }}<br/>
                <strong>{{ $data->professor->legal_identification }}</strong>
            </td>
            <td style="width: 50%;" colspan="2">Horas profesor de idiomas</td>
        </tr>
    </table>

    <table border="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tr>
            <th style="width: 50%;">Empresa</th>
            <th style="width: 50%;">Contacto</th>
        </tr>
        <tr>
            <td>
                Personal Learning Group<br/>
                <strong><sub>NIT. 901778510-2 </sub></strong>
            </td>
            <td>
                <strong>Tel/WP:</strong> <a href="https://wa.me/+573237608867">+57 3237608867</a><br/>
                <strong>Email:</strong> <a href="mailto:info@plgcolombia.com">info@plgcolombia.com</a>
            </td>
        </tr>
    </table>

    <table border="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tr>
            <th colspan="2" style="text-align:center">PERIODO FACTURADO</th>
        </tr>
        <tr>
            <th style="width: 50%;">Fecha Inicio</th>
            <th style="width: 50%;">Fecha Final</th>
        </tr>
        <tr>
            <td>{{ $data->start_date }}</td>
            <td>{{ $data->end_date }}</td>
        </tr>
        <tr>
            <th colspan="2" >Comentarios</th>
        </tr>
        <tr>
            <td colspan="2" >{{ $data->comments }}</td>
        </tr>
    </table>
    <table border="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tr>
            <th style="text-align:center" colspan="5">Detalle de servicios</th>
        </tr>
        <tr>
            <th>Detalle</th>
            <th>Comentarios</th>
            <th>Precio Hora</th>
            <th>Tiempo</th>
            <th>Total</th>
        </tr>
        @foreach ($data->imparted_class as $item)
            <tr>
                <td>Clase del {{ $item->scheduled_class }} del <br/>plan {{$item->contrated_plan->id}} [{{$item->contrated_plan->short_description}}]</td>
                <td>{{ $item->comments }}</td>
                <td>$ {{ ceil($item->contrated_plan->hourly_fee) }}</td>
                <td>{{ $item->class_duration }}</td>
                <td>$ {{ ceil($item->class_duration*$item->contrated_plan->hourly_fee) }}</td>
            </tr>
        @endforeach
        @foreach ($data->diagnostic_class as $item)
            <tr>
                <td>Clase Diagnóstico del {{ $item->starting_date }} <br/>para: {{$item->candidate_name}} [{{$item->candidate_email}}]</td>
                <td>{{ $item->comments }}</td>
                <td>$ {{ ceil($item->hourly_fee) }}</td>
                <td>{{ $item->class_duration }}</td>
                <td>$ {{ ceil($item->class_duration*$item->hourly_fee) }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3" style="text-align: right;">Total:</th>
            <td>{{ $data->total_time }} Hrs</td>
            <td>$ {{ ceil($data->total_value) }}</td>
        </tr>
    </table>

    <div class="signature-block">
        <img width="200px" style="margin-bottom: 10px;" src="{{$data->signature_img}}" alt="Descripción de la imagen"><br/>
        <div class="signature-line"></div>
        <p class="signature-name">
            {{$data->professor->user->name}}
            <br/>
            <sub>{{$data->professor->legal_identification}}</sub>
        </p>

    </div>

</body>
</html>
