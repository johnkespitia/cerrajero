<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKioskInvoiceDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kiosk_invoice_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("kiosk_invoices_id");
            $table->foreign('kiosk_invoices_id')->references('id')->on('kiosk_invoices');
            $table->unsignedBigInteger("kiosk_units_id");
            $table->foreign('kiosk_units_id')->references('id')->on('kiosk_units');
            $table->unsignedBigInteger("price");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kiosk_invoice_details');
    }
}
