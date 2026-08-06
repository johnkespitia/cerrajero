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
        Schema::table('kiosk_units', function (Blueprint $table) {
            $table->timestamp('transferred_at')->nullable()->after('sold')
                ->comment('Fecha en que la unidad fue trasladada al minibar');
            $table->index(['transferred_at'], 'kiosk_units_transferred_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kiosk_units', function (Blueprint $table) {
            $table->dropIndex('kiosk_units_transferred_idx');
            $table->dropColumn('transferred_at');
        });
    }
};
