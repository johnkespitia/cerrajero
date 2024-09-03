<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            //coordenadas
            $table->unsignedDecimal('latitude' , 8 , 2);
            $table->unsignedDecimal('longitude' , 8 , 2);
            //address and directions
            $table->string('address' , 200 );
            $table->string('arrival_directions')->nullable();
            $table->string('address_remarks')->nullable();
            //customer foreign key
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            //city foreign key
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('city');
            //state
            $table->boolean('state');
            $table->string('address_type');
            $table->string('is_principal')->nullable();
            $table->bigInteger('phone')->nullable();
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
        Schema::dropIfExists('addresses');
    }
}
