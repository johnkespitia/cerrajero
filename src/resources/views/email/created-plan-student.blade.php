@extends('email.template')

@section('contenido')
  <p>{{ $student->user->name }} Hemos agregado un plan de clases para ti.</p>
  <p>Puedes verificarlo ingresando a nuestra plataforma, los detalles del plan son los siguientes:</p>
  <div class="container-details">
    <div class="container-plan">
        <ul>
            <li><strong>Plan contratado: </strong>{{$plan->short_description}}</li>
            <li><strong>Detalles Extra: </strong>{{$plan->plan_extra_details}}</li>
            <li><strong>Fecha de Inicio: </strong>{{$plan->starting_date}}</li>
            <li><strong>Fecha de Finalización: </strong>{{$plan->expiration_date}}</li>
            <li><strong>Clases Contratadas: </strong>{{$plan->classes}}</li>
        </ul>
    </div>
    <div class="container-professor">
        <img src="{{$plan->professor->main_photo}}" class="professor-image"/>
        <div class="professor-details">
        <dl>
            <dt><strong>Nombre Profesor:</strong></dt>
            <dd>{{$plan->professor->user->name}}</dd>

            <dt><strong>Email:</strong></dt>
            <dd>{{$plan->professor->user->email}}</dd>

            <dt><strong>Acerca del profesor:</strong></dt>
            <dd>{{$plan->professor->brief_resume}}</dd>

            <dt><strong>Perfil:</strong></dt>
            <dd><a href="{{$plan->professor->cv_url}}" target="_blank">Ver Perfil</a></dd>
        </dl>
        </div>
    </div>
  </div>
    <p>Puedes ingresar a tu cuenta haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresa</a>

@endsection
