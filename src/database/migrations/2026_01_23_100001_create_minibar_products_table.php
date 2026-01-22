<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('minibar_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('minibar_product_categories')->onDelete('restrict');
            $table->boolean('is_sellable')->default(true)->comment('1=vendible, 0=no vendible');
            $table->decimal('sale_price', 10, 2)->nullable()->comment('Precio de venta (solo para vendibles)');
            $table->string('unit', 50)->default('unidad')->comment('Unidad de medida');
            $table->string('barcode', 125)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->integer('stock_alert_threshold')->nullable()->comment('Umbral de alerta de stock bajo');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('category_id');
            $table->index('is_sellable');
            $table->index('active');
            $table->unique('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minibar_products');
    }
};
