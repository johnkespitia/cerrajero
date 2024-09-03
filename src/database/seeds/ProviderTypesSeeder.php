<?php

use Illuminate\Database\Seeder;
use App\ProviderType;
class ProviderTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ProviderType::create(["name"=>"Mercado","description"=>"","status"=>true]);
        ProviderType::create(["name"=>"Restaurante","description"=>"","status"=>true]);
        ProviderType::create(["name"=>"Servicios","description"=>"","status"=>true]);
    }
}
