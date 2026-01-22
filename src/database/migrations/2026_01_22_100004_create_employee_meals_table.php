<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeMealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employee_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Empleado que consume la comida');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner']);
            $table->date('meal_date');
            $table->text('notes')->nullable()->comment('Observaciones sobre la comida');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Usuario que registra la comida');
            $table->timestamps();
            
            // Índices
            $table->index('user_id');
            $table->index('meal_type');
            $table->index('meal_date');
            $table->index('created_by');
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_meals');
    }
}
