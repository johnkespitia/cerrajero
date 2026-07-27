<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'web_payment_receipt_path')) {
                $table->string('web_payment_receipt_path', 500)->nullable()->after('web_payment_mode');
            }
            if (! Schema::hasColumn('reservations', 'web_payment_receipt_url')) {
                $table->string('web_payment_receipt_url', 500)->nullable()->after('web_payment_receipt_path');
            }
            if (! Schema::hasColumn('reservations', 'web_payment_receipt_uploaded_at')) {
                $table->timestamp('web_payment_receipt_uploaded_at')->nullable()->after('web_payment_receipt_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $columns = [
                'web_payment_receipt_path',
                'web_payment_receipt_url',
                'web_payment_receipt_uploaded_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
