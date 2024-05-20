@extends('email.template')

@section('contenido')
  <p>Estimado {{ $student->user->name }} has sido registrado como estudiante en nuestra plataforma.</p>
  <p>A través de la plataforma podrás:</p>
  <ul>
      <li>Consultar tus planes vigentes</li>
      <li>Acceder a tus clases</li>
      <li>Actualizar tu información de contacto</li>
    </ul>
    <p>Recuerda que ingresas con tu correo y el password por defecto es <code>STD{{$student->legal_identification}}@!</code></p>
    <p>Puedes ingresar a completar la información de tu cuenta haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresa</a>

@endsection
