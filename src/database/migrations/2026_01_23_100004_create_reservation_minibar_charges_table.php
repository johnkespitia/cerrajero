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
        Schema::create('reservation_minibar_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('inventory_record_id')->nullable()->constrained('room_minibar_inventory')->onDelete('set null');
            $table->foreignId('product_id')->constrained('minibar_products')->onDelete('restrict');
            $table->integer('quantity')->default(1)->comment('Cantidad consumida');
            $table->decimal('unit_price', 10, 2)->comment('Precio unitario al momento de la venta');
            $table->decimal('total', 10, 2)->comment('Total = quantity * unit_price');
            $table->timestamp('recorded_at')->useCurrent()->comment('Fecha/hora del consumo');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('record_type', ['cleaning', 'check_out'])->comment('Cuándo se registró el consumo');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('inventory_record_id');
            $table->index('product_id');
            $table->index('record_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_minibar_charges');
    }
};
