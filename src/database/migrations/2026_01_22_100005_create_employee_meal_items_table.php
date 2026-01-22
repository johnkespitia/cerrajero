<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeMealItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employee_meal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_meal_id');
            $table->unsignedBigInteger('recipe_id');
            $table->decimal('quantity', 8, 2)->unsigned();
            $table->unsignedBigInteger('measure_id');
            $table->decimal('inventory_cost', 10, 2)->default(0.00)->comment('Costo del inventario consumido');
            $table->timestamps();
            
            // Índices
            $table->index('employee_meal_id');
            $table->index('recipe_id');
            $table->index('measure_id');
            
            // Foreign keys
            $table->foreign('employee_meal_id')->references('id')->on('employee_meals')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id')->on('kitchen_recipes')->onDelete('restrict');
            $table->foreign('measure_id')->references('id')->on('inventory_measures')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_meal_items');
    }
}
