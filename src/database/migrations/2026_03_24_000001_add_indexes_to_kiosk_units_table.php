<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToKioskUnitsTable extends Migration
{
    /**
     * Run the migrations.
     * Optimización para la iniciativa inventory-sync-optimization
     */
    public function up()
    {
        Schema::table('kiosk_units', function (Blueprint $table) {
            $table->index('active');
            $table->index('sold');
            $table->index('expiration');
            // Índice compuesto para la consulta principal de inventario disponible
            $table->index(['product_id', 'active', 'sold'], 'kiosk_units_avail_idx');
        });
        
        Schema::table('kiosk_products', function (Blueprint $table) {
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('kiosk_units', function (Blueprint $table) {
            $table->dropIndex(['active']);
            $table->dropIndex(['sold']);
            $table->dropIndex(['expiration']);
            $table->dropIndex('kiosk_units_avail_idx');
        });
        
        Schema::table('kiosk_products', function (Blueprint $table) {
            $table->dropIndex(['active']);
        });
    }
}
