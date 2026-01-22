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
        Schema::create('minibar_restocking_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('minibar_products')->onDelete('restrict');
            $table->integer('quantity_added')->comment('Cantidad agregada');
            $table->integer('quantity_before')->comment('Cantidad antes de la reposición');
            $table->integer('quantity_after')->comment('Cantidad después de la reposición');
            $table->timestamp('restocked_at')->useCurrent()->comment('Fecha/hora de reposición');
            $table->foreignId('restocked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('reason', ['standard', 'after_checkout', 'low_stock', 'manual'])->default('standard')->comment('Razón de la reposición');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('room_id');
            $table->index('product_id');
            $table->index('restocked_at');
            $table->index('restocked_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minibar_restocking_log');
    }
};
