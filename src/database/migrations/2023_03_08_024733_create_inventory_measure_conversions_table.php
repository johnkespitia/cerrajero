<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryMeasureConversionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_measure_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("origin_id");
            $table->foreign("origin_id")
                ->references('id')
                ->on('inventory_measures')
                ->onDelete('cascade');
            $table->unsignedBigInteger("destination_id");
            $table->foreign("destination_id")
                ->references('id')
                ->on('inventory_measures')
                ->onDelete('cascade');
            $table->decimal("factor", 8,2,true);
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
        Schema::dropIfExists('inventory_measure_conversions');
    }
}
