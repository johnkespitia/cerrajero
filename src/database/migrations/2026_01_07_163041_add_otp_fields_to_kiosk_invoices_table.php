<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtpFieldsToKioskInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->string('otp_code', 6)->nullable()->after('reservation_id');
            $table->timestamp('otp_sent_at')->nullable()->after('otp_code');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_sent_at');
            $table->foreignId('otp_verified_by')->nullable()->after('otp_verified_at')->constrained('users')->onDelete('set null');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_verified_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->dropForeign(['otp_verified_by']);
            $table->dropColumn([
                'otp_code',
                'otp_sent_at',
                'otp_verified_at',
                'otp_verified_by',
                'otp_expires_at'
            ]);
        });
    }
}
