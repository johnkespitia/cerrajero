<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultPriceToKitchenRecipesTable extends Migration
{
    /**
     * Run the migrations.
     * Precio por defecto de venta del plato/receta en restaurante.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kitchen_recipes', function (Blueprint $table) {
            $table->decimal('default_price', 10, 2)->nullable()->after('measure_id')
                ->comment('Precio por defecto de venta del plato en restaurante');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kitchen_recipes', function (Blueprint $table) {
            $table->dropColumn('default_price');
        });
    }
}
