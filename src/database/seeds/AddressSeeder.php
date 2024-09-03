<?php

use Illuminate\Database\Seeder;
use App\Address;

class AddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Address::create([
            'latitude' => 50.6,
            'longitude' => 50.6,
            'address' => "Calle 4 89 -65",
            'arrival_directions' => "Arribita del aguacaño",
            'address_remarks' => "Barrio Antioquia",
            'customer_id' => 1,
            'city_id'=> 1,
            'address_type'=> 'CASA',
            'phone' => 3228472310,
            'is_principal' => true,
            'state' => false,
        ]);
    }
}
