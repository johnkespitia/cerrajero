<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToInventoryIngredientsMeasures extends Migration
{
    public function up()
    {
        Schema::table('kitchen_recipe_ingredients', function (Blueprint $table) {
            $table->unsignedBigInteger('measure_id')->nullable();
            $table->foreign('measure_id')->references('id')->on('inventory_measures');
        });
    }

    public function down()
    {
        Schema::table('kitchen_recipe_ingredients', function (Blueprint $table) {
            $table->dropForeign(['measure_id']);
            $table->dropColumn('measure_id');
        });
    }
}
