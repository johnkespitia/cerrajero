<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE additional_services MODIFY meal_type ENUM('breakfast', 'lunch', 'dinner', 'refreshment') NULL");
        DB::statement("ALTER TABLE orders MODIFY meal_type ENUM('breakfast', 'lunch', 'dinner', 'refreshment') NULL");
        DB::statement("ALTER TABLE reservation_meal_consumption MODIFY meal_type ENUM('breakfast', 'lunch', 'dinner', 'refreshment') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE additional_services SET meal_type = NULL WHERE meal_type = 'refreshment'");
        DB::statement("ALTER TABLE additional_services MODIFY meal_type ENUM('breakfast', 'lunch', 'dinner') NULL");

        DB::statement("UPDATE orders SET meal_type = NULL WHERE meal_type = 'refreshment'");
        DB::statement("ALTER TABLE orders MODIFY meal_type ENUM('breakfast', 'lunch', 'dinner') NULL");

        DB::statement("DELETE FROM reservation_meal_consumption WHERE meal_type = 'refreshment'");
        DB::statement("ALTER TABLE reservation_meal_consumption MODIFY meal_type ENUM('breakfast', 'lunch', 'dinner') NOT NULL");
    }
};
