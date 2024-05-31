@extends('email.template')



@section('contenido')
<style>

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
  <p>{{ $student->user->name }} Hemos agregado clases a tu plan.</p>
  <p>Puedes verificarlas ingresando a nuestra plataforma, los detalles de la clase son los siguientes:</p>
  <div class="container-details">
    <div class="container-plan">
        @foreach($plan->imparted_classes as $k=>$class)
        <?php $n =  $k+1; ?>
        <ul>
            <li><strong>Clase #{{$n}} </strong></li>
            <li><strong>Fecha de Clase: </strong>{{$class->scheduled_class}}</li>
            <li><strong>Hora de la Clase: </strong>{{$class->class_time}}</li>
            <li><strong>Observaciones: </strong>{{$class->comments}}</li>
        </ul>
        @endforeach
    </div>
  </div>
  <p>Agregalas a tu calendario:</p>
<ul>
  @foreach($classes as $key => $link)
  <?php $n =  $key+1; ?>
  <li> <strong>Clase #{{$n}}:  </strong>
  <a class="custom-button google" href="{{$link->google()}}">google</a>
  <a class="custom-button yahoo" href="{{$link->yahoo()}}">yahoo</a>
  <a class="custom-button office" href="{{$link->webOffice()}}">office</a>
  <a class="custom-button hotmail" href="{{$link->webOutlook()}}">hotmail</a>
</li>
  @endforeach
</ul>
    <p>Puedes ingresar a tu cuenta haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresa</a>

@endsection
