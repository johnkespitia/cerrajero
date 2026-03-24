<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryConsumptionLogTable extends Migration
{
    /**
     * Registro del valor consumido de materia prima por ítem de orden (conversión de medida aplicada).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_consumption_log', function (Blueprint $table) {
            $table->id();
            // Este registro también se usa para consumos de comidas de empleados,
            // donde no existe un order_item_id asociado.
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('inventory_batch_id');
            $table->unsignedBigInteger('input_id');
            $table->decimal('quantity_consumed', 12, 4)->comment('Cantidad descontada en la medida del lote');
            $table->unsignedBigInteger('measure_id')->nullable()->comment('Medida en que se expresa el consumo para reportes');
            $table->timestamps();

            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
            $table->foreign('inventory_batch_id')->references('id')->on('inventory_batches')->onDelete('cascade');
            $table->foreign('input_id')->references('id')->on('inventory_inputs')->onDelete('cascade');
            $table->foreign('measure_id')->references('id')->on('inventory_measures')->onDelete('set null');

            $table->index(['order_item_id', 'input_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_consumption_log');
    }
}
