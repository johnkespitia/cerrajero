@extends('email.template')

@section('contenido')
  <p>Estimado {{ $professor->user->name }} has sido registrado como profesor en nuestra plataforma.</p>
  <p>A través de la plataforma podrás:</p>
  <ul>
      <li>Consultar tus planes vigentes</li>
      <li>Agendar clases</li>
      <li>Actualizar tu información de contacto</li>
      <li>Generar cuentas de cobro</li>
      <li>Consultar cuentas de cobro</li>
    </ul>
    <p>Recuerda que ingresas con tu correo y el password por defecto es <code>PFS{{$professor->legal_identification}}@!</code></p>
    <p>Puedes ingresar a completar la información de tu cuenta haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresa</a>

@endsection
