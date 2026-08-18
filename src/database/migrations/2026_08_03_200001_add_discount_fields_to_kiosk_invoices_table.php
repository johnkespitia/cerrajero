<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountFieldsToKioskInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('payment_code');
            $table->decimal('coupon_discount', 10, 2)->default(0)->after('coupon_code');
            $table->decimal('manual_discount', 10, 2)->default(0)->after('coupon_discount');
            $table->unsignedBigInteger('manual_discount_by')->nullable()->after('manual_discount');
            $table->decimal('discount_total', 10, 2)->default(0)->after('manual_discount_by');

            $table->foreign('manual_discount_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->dropForeign(['manual_discount_by']);
            $table->dropColumn([
                'coupon_code',
                'coupon_discount',
                'manual_discount',
                'manual_discount_by',
                'discount_total',
            ]);
        });
    }
}
