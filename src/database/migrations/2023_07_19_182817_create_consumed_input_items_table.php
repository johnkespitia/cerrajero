<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsumedInputItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consumed_input_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("quantity")->default(0);
            $table->string("description", 255)->nullable();
            $table->unsignedBigInteger("recipe_ingredient_id");
            $table->unsignedBigInteger("order_item_id");
            $table->foreign('recipe_ingredient_id')->references('id')->on('kitchen_recipe_ingredients');
            $table->foreign('order_item_id')->references('id')->on('order_items');
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
        Schema::dropIfExists('consumed_input_items');
    }
}
