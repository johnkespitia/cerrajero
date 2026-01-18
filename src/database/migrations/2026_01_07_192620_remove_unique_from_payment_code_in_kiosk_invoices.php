<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUniqueFromPaymentCodeInKioskInvoices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            // Eliminar el índice único del campo payment_code
            $table->dropUnique(['payment_code']);
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
            // Restaurar el índice único del campo payment_code
            $table->unique('payment_code');
        });
    }
}
