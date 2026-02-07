<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowEmployeeMealInConsumptionLog extends Migration
{
    /**
     * Permite registrar consumo por comidas de trabajadores (order_item_id nullable, employee_meal_item_id opcional).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inventory_consumption_log', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_meal_item_id')->nullable()->after('order_item_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE inventory_consumption_log MODIFY order_item_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE inventory_consumption_log ALTER COLUMN order_item_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite no permite cambiar NOT NULL fácilmente; se asume que la tabla puede tener order_item_id NULL
            DB::statement('PRAGMA table_info(inventory_consumption_log)');
        }

        Schema::table('inventory_consumption_log', function (Blueprint $table) {
            $table->foreign('employee_meal_item_id')
                ->references('id')
                ->on('employee_meal_items')
                ->onDelete('cascade');
            $table->index('employee_meal_item_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_consumption_log', function (Blueprint $table) {
            $table->dropForeign(['employee_meal_item_id']);
            $table->dropIndex(['employee_meal_item_id']);
        });
        Schema::table('inventory_consumption_log', function (Blueprint $table) {
            $table->dropColumn('employee_meal_item_id');
        });
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE inventory_consumption_log MODIFY order_item_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE inventory_consumption_log ALTER COLUMN order_item_id SET NOT NULL');
        }
    }
}
