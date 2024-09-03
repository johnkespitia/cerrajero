<?php

use Illuminate\Database\Seeder;
use App\OrderStatus;
class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        OrderStatus::create(["name" => "Pendiente de Pago","icon"=>"PendientePago.png","status"=>true]);
        OrderStatus::create(["name" => "Comprado","icon"=>"Comprado.png","status"=>true]);
        OrderStatus::create(["name" => "En Despacho","icon"=>"EnDespacho.png","status"=>true]);
        OrderStatus::create(["name" => "Estregado","icon"=>"Entregado.png","status"=>true]);
        OrderStatus::create(["name" => "Rechazado","icon"=>"Rechazado.png","status"=>true]);
        OrderStatus::create(["name" => "Cancelado","icon"=>"Cancelado.png","status"=>true]);
    }
}
