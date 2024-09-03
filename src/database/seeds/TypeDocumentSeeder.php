<?php

use Illuminate\Database\Seeder;
use App\DocumentType;

class TypeDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DocumentType::create(["tipo_documento" => "CC"]);
        DocumentType::create(["tipo_documento" => "TI"]);
        DocumentType::create(["tipo_documento" => "CE"]);
    }
}
