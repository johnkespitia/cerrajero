<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProducedBatchesTable extends Migration
{
    public function up()
    {
        Schema::create('produced_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->foreign('order_item_id')->references('id')->on('order_items');
            $table->unsignedDecimal('quantity', 8, 2);
            $table->date('expiration_date');
            $table->string("batch_serial",120);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('produced_batches');
    }
}
