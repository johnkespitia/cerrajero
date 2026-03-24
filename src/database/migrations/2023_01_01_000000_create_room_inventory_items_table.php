<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomInventoryItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('room_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('purchase_unit')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('presentation_unit')->nullable();
            $table->integer('quantity_per_presentation')->default(1);
            $table->integer('min_qty_notify')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on('inventory_categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_inventory_items');
    }
}
