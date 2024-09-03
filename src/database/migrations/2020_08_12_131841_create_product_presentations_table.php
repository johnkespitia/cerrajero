<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPresentationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_presentations', function (Blueprint $table) {
            $table->id();
            $table->string("name_presentation", 100);
            $table->string("internal_sku", 25);
            $table->text("special_description")->nullable();
            $table->boolean("status");
            $table->foreignId("product_id")->constrained("products");
            $table->engine = 'InnoDB';
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_presentations');
    }
}
