<?php

use Illuminate\Database\Seeder;
use App\City;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        City::create(['ciudad' => 'Tebaida']);
        City::create(['ciudad' => 'Bogotá']);
        City::create(['ciudad' => 'Medellín']);
    }
}
