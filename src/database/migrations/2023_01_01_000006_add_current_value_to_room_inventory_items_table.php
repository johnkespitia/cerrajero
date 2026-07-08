<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrentValueToRoomInventoryItemsTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_inventory_items') || Schema::hasColumn('room_inventory_items', 'current_value')) {
            return;
        }

        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->decimal('current_value', 10, 2)->nullable()->after('purchase_price')->comment('Valor actual o estimado del artículo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_inventory_items') || ! Schema::hasColumn('room_inventory_items', 'current_value')) {
            return;
        }

        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->dropColumn('current_value');
        });
    }
}
