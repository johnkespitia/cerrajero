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
        Schema::create('kiosk_minibar_product_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kiosk_product_id')->constrained('kiosk_products')->onDelete('cascade');
            $table->foreignId('minibar_product_id')->constrained('minibar_products')->onDelete('cascade');
            $table->enum('match_source', ['auto', 'manual'])->default('manual')
                ->comment('auto=coincidencia sugerida aceptada, manual=elegida por el usuario');
            $table->timestamps();

            $table->unique('kiosk_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_minibar_product_map');
    }
};
