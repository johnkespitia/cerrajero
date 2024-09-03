<?php

use Illuminate\Database\Seeder;
use App\ProductPriceStatus;
class OrderPriceStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ProductPriceStatus::create(["name" => "Comprado", "icon"=>"Comprado.png","status"=>true]);
        ProductPriceStatus::create(["name" => "En Alistamiento", "icon"=>"En-Alistamiento.png","status"=>true]);
        ProductPriceStatus::create(["name" => "Recogido", "icon"=>"Recogido.png","status"=>true]);
        ProductPriceStatus::create(["name" => "En Transporte", "icon"=>"En-Transporte.png","status"=>true]);
        ProductPriceStatus::create(["name" => "Entregado", "icon"=>"Entregado.png","status"=>true]);
        ProductPriceStatus::create(["name" => "Rechazado", "icon"=>"Rechazado.png","status"=>true]);
    }
}
