<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMealTypeToAdditionalServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner'])->nullable()->after('applies_to');
            $table->boolean('is_food_service')->default(false)->after('meal_type');
            
            // Índices
            $table->index('meal_type');
            $table->index('is_food_service');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropIndex(['meal_type']);
            $table->dropIndex(['is_food_service']);
            
            $table->dropColumn(['meal_type', 'is_food_service']);
        });
    }
}
