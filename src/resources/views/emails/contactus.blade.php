<!DOCTYPE html>
<html>

<head>
  <title>Mensaje desde la página de Campo Verde</title>
</head>

<body>

  <h1>Mensaje desde la página de Campo Verde</h1>
  <p>Hemos recibiso un mensaje desde la página de Campo Verdepara que te comuniques lo más pronto posible, los datos proporcionados son:</p>

  <ul>
    <li><strong>Nombre:</strong> {{$messageInfo["Nombre"]}}</li>
    <li><strong>Email:</strong> {{$messageInfo["Correo"]}}</li>
    <li><strong>Teléfono:</strong> {{$messageInfo["Teléfono"]}}</li>
    <li><strong>Mensaje:</strong> {{$messageInfo["Mensaje"]}}</li>
  </ul>

  <p>Saludos</p>
  <p>Campo Verde</p>
</body>

</html>