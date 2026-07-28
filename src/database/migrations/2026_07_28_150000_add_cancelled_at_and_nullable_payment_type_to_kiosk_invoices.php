<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('payed');
        });

        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
        });

        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_type_id')->nullable()->change();
            $table->foreign('payment_type_id')->references('id')->on('payment_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
        });

        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_type_id')->nullable(false)->change();
            $table->foreign('payment_type_id')->references('id')->on('payment_types');
            $table->dropColumn('cancelled_at');
        });
    }
};
