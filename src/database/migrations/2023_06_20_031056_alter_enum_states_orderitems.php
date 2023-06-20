<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterEnumStatesOrderitems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //enum('pending', 'preparing', 'prepared', 'delivered')
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('status', ['Pendiente', 'En Producción', 'Producto Sin Empaque', 'Producto Empacado', 'Despachado']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('status', ['pending', 'preparing', 'prepared', 'delivered'])->nullable();
        });
    }
}
