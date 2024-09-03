<?php

use Illuminate\Database\Seeder;
use App\PaymentMethod as Pmethods;
class PaymentMethods extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Pmethods::create(["name" => "Pago Contraentrega", "status"=>true]);
        Pmethods::create(["name" => "MercadoPago", "status"=>true]);
    }
}
