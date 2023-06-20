<!DOCTYPE html>
<html lang="es:la">
<head>
    <meta charset="UTF-8">
    <title>Alerta de materia prima próximo a quedarse sin stock</title>
</head>
<body>
<h1>Alerta de materia prima próximo a quedarse sin stock</h1>

<p>Este mensaje es para informarte que tenemos una materia prima proxima quedarse sin inventario, a continuacion encontraras el detalle del producto y el total restante</p>

<ul>
    <li><strong>Producto:</strong> {{ $inventoryInput->name }}</li>
    <li><strong>Cantidad en stock:</strong> {{ $currentStock }} {{ $inventoryInput->measure->name }}(s)</li>
</ul>

<p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.</p>

<p>¡Disfruta de tu experiencia en  {{env("APP_NAME")}}!</p>

<p>Saludos,</p>
<p>El equipo de  {{env("APP_NAME")}}</p>
</body>
</html>
