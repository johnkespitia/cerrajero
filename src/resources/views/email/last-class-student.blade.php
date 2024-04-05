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
  <p>{{ $student->user->name }} Te queda una clase :o.</p>
  <p>Queremos que continus tu proceso y no te detengas por lo que te invitamos a adquirir un nuevo ciclo y continuar fortaleciendo tu nuevo idioma</p>

    <p>Puedes ingresar a tu cuenta haciendo clic en el siguiente enlace:</p>
    <a href="{{$main_btn_url}}">Ingresar</a>

@endsection
