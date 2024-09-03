<!DOCTYPE html>
<html>
<head>
    <title>Solicitud para la orden #{{$order}}</title>
</head>

<body>

<h1>Hola, el cliente {{$user->name}} ha realizado una solicitud</h1>
<p>ingrese a la orden #{{$order}} y de atención a esta solicitud.</p>

<ul>
    <li><strong>Mensaje:</strong>  {{print_r($message,1)}}</li>
    
    <li><strong>Estado:</strong> </li>
</ul>

<p>Saludos</p>





</body>

</html>