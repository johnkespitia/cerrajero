<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomInventoryItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('room_inventory_categories')->onDelete('set null');
            $table->string('brand', 125)->nullable();
            $table->string('model', 125)->nullable();
            $table->string('serial_number', 125)->nullable()->unique();
            $table->string('barcode', 125)->nullable()->unique();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('category_id');
            $table->index('active');
            $table->index('serial_number');
            $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_inventory_items');
    }
}
