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
        Schema::create('minibar_expired_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('minibar_products')->onDelete('cascade');
            $table->integer('quantity')->comment('Cantidad reportada como vencida');
            $table->timestamp('recorded_at')->useCurrent()->comment('Fecha del reporte');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que reporta');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('recorded_at');
            $table->index('recorded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minibar_expired_log');
    }
};
