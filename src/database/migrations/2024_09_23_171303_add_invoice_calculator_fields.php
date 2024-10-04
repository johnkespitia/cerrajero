<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvoiceCalculatorFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->unsignedInteger("payed_value")->nullable();
            $table->unsignedInteger("remain_money")->nullable();
            $table->boolean("electronic_invoice")->nullable();
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
            $table->dropColumn("payed_value");
            $table->dropColumn("remain_money");
            $table->dropColumn("electronic_invoice");
        });
    }
}
