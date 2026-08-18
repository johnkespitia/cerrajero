<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueQrCodeToRoomInventoryItemsTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_inventory_items')
            || ! Schema::hasColumn('room_inventory_items', 'qr_code')) {
            return;
        }

        if ($this->indexExists('room_inventory_items_qr_code_unique')) {
            return;
        }

        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->unique('qr_code', 'room_inventory_items_qr_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_inventory_items')) {
            return;
        }

        if (! $this->indexExists('room_inventory_items_qr_code_unique')) {
            return;
        }

        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->dropUnique('room_inventory_items_qr_code_unique');
        });
    }

    protected function indexExists(string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $result = Schema::getConnection()->select(
            'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = ? AND index_name = ?',
            [$database, $indexName]
        );
        return ((int) ($result[0]->total ?? 0)) > 0;
    }
}