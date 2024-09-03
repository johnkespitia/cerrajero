<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceCitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_origin')->constrained('city');
            $table->foreignId('city_destination')->constrained('city');
            $table->integer('price',false, true);
            $table->text('observations');
            $table->timestamps();
            $table->engine = 'InnoDB';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_cities');
    }
}
