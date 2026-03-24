<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrentValueToRoomInventoryItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->decimal('current_value', 10, 2)->nullable()->after('purchase_price')->comment('Valor actual o estimado del artículo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('room_inventory_items', function (Blueprint $table) {
            $table->dropColumn('current_value');
        });
    }
}
