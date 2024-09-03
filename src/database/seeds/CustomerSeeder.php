<?php

use Illuminate\Database\Seeder;
use App\Customer;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Customer::create([
                'document' => 80048286,
                'nombre' => 'Jhon',
                'apellido' => 'Doe',
                'genre' => 'm',
                'state' => 1,
                'user_id' => 4,
                'tipo_doc_id' => 1
        ]);


    }
}
