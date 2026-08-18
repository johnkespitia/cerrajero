<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEffectAndScopeToKioskCouponsTable extends Migration
{
    public function up()
    {
        Schema::table('kiosk_coupons', function (Blueprint $table) {
            $table->enum('effect', ['discount', 'increment'])->default('discount')->after('type');
            $table->enum('apply_scope', ['cart', 'item'])->default('cart')->after('effect');
        });

        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->enum('coupon_effect', ['discount', 'increment'])->nullable()->after('coupon_code');
            $table->enum('coupon_apply_scope', ['cart', 'item'])->nullable()->after('coupon_effect');
        });
    }

    public function down()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->dropColumn(['coupon_effect', 'coupon_apply_scope']);
        });

        Schema::table('kiosk_coupons', function (Blueprint $table) {
            $table->dropColumn(['effect', 'apply_scope']);
        });
    }
}
