<?php

use Illuminate\Database\Seeder;
use App\PriceCity;
class PricesCities extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //Origin 1
        
        PriceCity::create([
            'city_origin' => 1,
            'city_destination' => 1,
            'price' => 2000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 1,
            'city_destination' => 2,
            'price' => 4500,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 1,
            'city_destination' => 3,
            'price' => 7000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 1,
            'city_destination' => 4,
            'price' => 8500,
            'observations' => '']
        );


        //Origin 2 
        PriceCity::create([
            'city_origin' => 2,
            'city_destination' => 1,
            'price' => 4500,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 2,
            'city_destination' => 2,
            'price' => 2000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 2,
            'city_destination' => 3,
            'price' => 7000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 2,
            'city_destination' => 4,
            'price' => 8500,
            'observations' => '']
        );

        //Origin 3 
        PriceCity::create([
            'city_origin' => 3,
            'city_destination' => 1,
            'price' => 7000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 3,
            'city_destination' => 2,
            'price' => 4500,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 3,
            'city_destination' => 3,
            'price' => 2000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 3,
            'city_destination' => 4,
            'price' => 4500,
            'observations' => '']
        );

        //Origin 4 
        PriceCity::create([
            'city_origin' => 4,
            'city_destination' => 1,
            'price' => 8500,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 4,
            'city_destination' => 2,
            'price' => 7000,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 4,
            'city_destination' => 3,
            'price' => 4500,
            'observations' => '']
        );

        PriceCity::create([
            'city_origin' => 4,
            'city_destination' => 4,
            'price' => 2000,
            'observations' => '']
        );
    }
}
