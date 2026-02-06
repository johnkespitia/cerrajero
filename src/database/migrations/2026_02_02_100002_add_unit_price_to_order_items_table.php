<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnitPriceToOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     * Precio unitario al que se vendió el plato en esta orden (permite override del default).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('measure_id')
                ->comment('Precio unitario de venta del plato en esta orden');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
}
