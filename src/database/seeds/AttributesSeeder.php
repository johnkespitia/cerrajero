<?php

use Illuminate\Database\Seeder;
use App\Attribute;
class AttributesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Attribute::create(["name"=>"Marca"]);
    }
}
