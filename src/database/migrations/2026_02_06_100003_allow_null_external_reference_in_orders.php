<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite que external_reference sea opcional (NULL) en órdenes.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('external_reference', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('external_reference', 20)->nullable(false)->change();
        });
    }
};
