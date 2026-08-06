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
        Schema::create('kiosk_unit_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_batch_id', 36)->index();
            $table->foreignId('unit_id')->constrained('kiosk_units')->onDelete('cascade');
            $table->foreignId('kiosk_product_id')->constrained('kiosk_products')->onDelete('restrict');
            $table->foreignId('minibar_product_id')->constrained('minibar_products')->onDelete('restrict');
            $table->date('expiration')->nullable()->comment('Snapshot de la fecha de vencimiento de la unidad');
            $table->foreignId('transferred_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('notes', 500)->nullable();
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index(['kiosk_product_id'], 'kiosk_unit_transfers_kiosk_product_idx');
            $table->index(['minibar_product_id'], 'kiosk_unit_transfers_minibar_product_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_unit_transfers');
    }
};
