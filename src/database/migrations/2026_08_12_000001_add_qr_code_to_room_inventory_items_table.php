<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQrCodeToRoomInventoryItemsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_inventory_items') && Schema::hasColumn('room_inventory_items', 'qr_code')) {
            return;
        }

        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->string('qr_code', 500)->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });
    }
}