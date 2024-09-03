<!DOCTYPE html>
<html>
<head>
    <title>Orden generada #{{$order->id}}</title>
</head>

<body>

<h1>Hola {{$user->name}}</h1>
<p>Gracias por comprar en Campo Verde, hemos recibido tu orden y la hemos nombrado Orden #{{$order->id}}.</p>

<ul>
    <li><strong>Dirección:</strong>  {{$address["address"]}}</li>
    <li><strong>Barrio:</strong>  {{$address["address_remarks"]}}</li>
    <li><strong>Indicaciones:</strong>  {{$address["arrival_directions"]}}</li>
    <li><strong>Teléfono:</strong>  {{$address["phone"]}}</li>
</ul>
<div style="text-align: center; width:100%;">
<a href="{{$link}}" style="background-color: #ffc107;
  color: white;
  padding: 14px 25px;
  text-align: center;
  text-decoration: none;
  display: inline-block;">Ver detalle de la orden</a>
</div>

<p>Recuerda que el valor de tu orden es de $ {{$order->total}} y debes cancelarlo al momento de recibir tu pedido</p>

<p>Saludos</p>





</body>

</html>