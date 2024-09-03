<?php

use Illuminate\Database\Seeder;
use App\City;
class changes_parcero_cities extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        City::where('ciudad', 'La Tebaida')->update(["ciudad"=>"Zona A (Urbana menor a 4 km)"]);
        City::create(["ciudad"=>"Zona B (Menor a 4 Km)"]);
        City::create(["ciudad"=>"Zona C (Entre 4 y 12 Km)"]);
        City::create(["ciudad"=>"Zona D (Mayor a 12 Km)"]);
        
    }
}
