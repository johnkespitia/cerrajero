<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRestaurantFieldsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('reservation_id')->nullable()->after('customer_id');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner'])->nullable()->after('price');
            $table->boolean('charge_to_room')->default(false)->after('meal_type');
            $table->unsignedBigInteger('payment_type_id')->nullable()->after('charge_to_room');
            $table->boolean('paid')->default(false)->after('payment_type_id');
            $table->boolean('inventory_verified')->default(false)->after('paid')->comment('Indica si se verificó el inventario al crear la orden');
            $table->timestamp('inventory_verification_date')->nullable()->after('inventory_verified')->comment('Fecha de verificación de inventario');
            
            // Índices
            $table->index('customer_id');
            $table->index('reservation_id');
            $table->index('meal_type');
            $table->index('inventory_verified');
            
            // Foreign keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('set null');
            $table->foreign('payment_type_id')->references('id')->on('payment_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['payment_type_id']);
            
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['reservation_id']);
            $table->dropIndex(['meal_type']);
            $table->dropIndex(['inventory_verified']);
            
            $table->dropColumn([
                'customer_id',
                'reservation_id',
                'meal_type',
                'charge_to_room',
                'payment_type_id',
                'paid',
                'inventory_verified',
                'inventory_verification_date'
            ]);
        });
    }
}
