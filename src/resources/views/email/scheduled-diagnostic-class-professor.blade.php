@extends('email.template')

<style>
    .container-details{
        display:flex;
        justify-content: space-between;
    }
    .container-plan{

    }
    .container-plan ul{
        list-style: none;
	    padding: 0;
    }
    .container-plan ul li + li {
        margin-top: 1rem;
    }
    .container-plan ul li {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: aliceblue;
        padding: 1.5rem;
        border-radius: 1rem;
        width: calc(100% - 2rem);
        box-shadow: 0.25rem 0.25rem 0.75rem rgb(0 0 0 / 0.1);
    }
    .container-plan ul li:nth-child(even) {
        flex-direction: row-reverse;
        background: honeydew;
        margin-right: -2rem;
        margin-left: 2rem;
    }
    .container-professor{
        display:flex;
        justify-content: space-between;
    }
    .professor-image{
        aspect-ratio: 1 / 1;
        min-width: 20%;
        border-radius: 10px;
    }
    .professor-details{
        min-width: 80%;
    }
</style>

@section('contenido')
  <p>{{ $class->professor->user->name }} Hemos agregado una clase diagnistoico que debes atender.</p>
  <p>Puedes verificarlo ingresando a nuestra plataforma, los detalles del plan son los siguientes:</p>
  <div class="container-details">
    <div class="container-plan">
        <ul>
        `<li><strong>Fecha de Clase: </strong>{{$class->starting_date}}</li>
            <li><strong>Hora de la Clase: </strong>{{$class->starting_time}}</li>
            <li><strong>Observaciones: </strong>{{$class->comments}}</li>
            <li><strong>Estudiante: </strong>{{$class->candidate_name}} <a href='mailto:{{$class->candidate_email}}'>Enviar Mensaje</a></li>
        </ul>
    </div>
  </div>
  <p>Agregalo a tu calendario:</p>

  @foreach($event_links as $key => $link)
    <a class="custom-button {{$key}}" href="{{$link}}">{{$key}}</a>
  @endforeach
    <p>Puedes ingresar a tu cuenta haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresa</a>

@endsection
