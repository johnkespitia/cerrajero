<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationMealConsumptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservation_meal_consumption', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner']);
            $table->unsignedInteger('quantity_consumed')->default(1);
            $table->boolean('is_included')->default(false)->comment('Si es parte del servicio adicional incluido');
            $table->boolean('is_additional')->default(false)->comment('Si es consumo adicional (se cobra aparte)');
            $table->date('consumption_date');
            $table->timestamps();
            
            // Índices
            $table->index('reservation_id');
            $table->index('order_id');
            $table->index('meal_type');
            $table->index('consumption_date');
            
            // Foreign keys
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservation_meal_consumption');
    }
}
