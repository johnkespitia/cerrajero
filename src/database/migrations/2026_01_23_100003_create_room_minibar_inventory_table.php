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
        Schema::create('room_minibar_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('minibar_products')->onDelete('restrict');
            $table->integer('initial_quantity')->default(0)->comment('Cantidad inicial al check-in');
            $table->integer('current_quantity')->default(0)->comment('Cantidad actual (se actualiza en limpieza/checkout)');
            $table->integer('consumed_quantity')->default(0)->comment('Cantidad consumida (calculada)');
            $table->timestamp('recorded_at')->useCurrent()->comment('Fecha/hora del registro');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('record_type', ['check_in', 'cleaning', 'check_out'])->default('check_in')->comment('Tipo de registro');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('room_id');
            $table->index('product_id');
            $table->index('record_type');
            $table->index('recorded_by');
            $table->index(['reservation_id', 'product_id', 'record_type'], 'reservation_product_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_minibar_inventory');
    }
};
