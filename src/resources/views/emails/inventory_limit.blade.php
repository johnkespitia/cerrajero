@extends('emails.layouts.base')

@section('title', 'Alerta de Inventario - ' . env('APP_NAME', 'Campo Verde'))

@section('header-title')
    <h1>ALERTA DE INVENTARIO</h1>
    <div class="subtitle">CAMPO VERDE</div>
@endsection

@section('content')
    <p>Este mensaje es para informarte que tenemos una materia prima próxima a quedarse sin inventario. A continuación encontrarás el detalle del producto y el total restante.</p>

    <div class="info-block" style="background-color: #fff3cd; border-left-color: #ffc107;">
        <h3>Detalle del Producto</h3>
        <div class="info-block-two-columns">
            <div class="info-block-left">
                <div class="info-label">PRODUCTO:</div>
                <div class="info-label">CANTIDAD EN STOCK:</div>
            </div>
            <div class="info-block-right">
                <div class="info-value">{{ $inventoryInput->name }}</div>
                <div class="info-value">{{ $currentStock }} {{ $inventoryInput->measure->name }}(s)</div>
            </div>
        </div>
    </div>

    <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos.</p>
@endsection
