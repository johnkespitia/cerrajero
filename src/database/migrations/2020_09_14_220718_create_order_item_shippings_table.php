<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderItemShippingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_item_shippings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('carriers');
            $table->foreignId('order_price_id')->constrained('order_prices');
            $table->string("shipping_status",30)->nullable();
            $table->string("traking_code",30)->nullable();
            $table->integer("price_shipping");
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
        Schema::dropIfExists('order_item_shippings');
    }
}
