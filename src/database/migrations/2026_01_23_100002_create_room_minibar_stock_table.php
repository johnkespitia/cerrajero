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
        Schema::create('room_minibar_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('minibar_products')->onDelete('restrict');
            $table->integer('standard_quantity')->default(0)->comment('Cantidad estándar que debe tener la habitación');
            $table->integer('current_quantity')->default(0)->comment('Cantidad actual en la habitación');
            $table->timestamp('last_restocked_at')->nullable()->comment('Última fecha de reposición');
            $table->foreignId('last_restocked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->unique(['room_id', 'product_id'], 'room_product_unique');
            $table->index('room_id');
            $table->index('product_id');
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_minibar_stock');
    }
};
