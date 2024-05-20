@extends('email.template')

@section('contenido')
<p>La cuenta de cobro que enviaste ha sido aprobada a través de nuestra plataforma.</p>
  <p>A continuación encontrarás detalles de la cuenta de cobro:</p>
  <ul>
      <li><strong>ID:</strong> {{$invoice->id}}</li>
      <li><strong>Fecha de Emisión:</strong> {{$invoice->generation_time}}</li>
      <li><strong>Periodo:</strong> Desde {{$invoice->start_date}} hasta {{$invoice->end_date}}</li>
      <li><strong>Total Horas:</strong> {{$invoice->total_time}}</li>
      <li><strong>Total a pagar:</strong> $ {{ceil($invoice->total_value)}}</li>
      <li><strong>Comentarios</strong> {{$invoice->comments}}</li>
    </ul>
    <p>Puedes ingresar a validar la información haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresar</a>

@endsection
