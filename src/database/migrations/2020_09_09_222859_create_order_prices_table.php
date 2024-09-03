<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_prices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('product_price_id')->constrained('product_presentation_provider_prices');
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('price_status_id')->constrained('product_price_statuses');
            $table->integer("price",false,true);
            $table->integer("price_provider",false,true);
            $table->integer("tax",false,true);
            $table->tinyInteger("quantity",false,true);
            $table->integer("total_product",false,true);
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
        Schema::dropIfExists('order_prices');
    }
}
