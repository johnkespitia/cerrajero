<?php

use Illuminate\Database\Seeder;
use App\PaymentMethod as Pmethods;
class AddCouponMethod extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Pmethods::create(["name" => "Coupons", "status"=>true]);
    }
}
