<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations') || Schema::hasColumn('reservations', 'web_payment_mode')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('web_payment_mode', 20)->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations') || ! Schema::hasColumn('reservations', 'web_payment_mode')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('web_payment_mode');
        });
    }
};
