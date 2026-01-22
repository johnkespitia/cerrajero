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
        Schema::create('minibar_product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 125);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable()->comment('Icono para UI');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minibar_product_categories');
    }
};
