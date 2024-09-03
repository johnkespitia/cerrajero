<?php

use Illuminate\Database\Seeder;
use App\Rol;
class RolStartup extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        Rol::create([
            'name' => 'root'
        ]);
        Rol::create([
            'name' => 'admin'
        ]);
        Rol::create([
            'name' => 'agent'
        ]);
        Rol::create([
            'name' => 'customer'
        ]);
    }
}
