<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClosureIdToKioskInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->foreignId('closure_id')
                ->nullable()
                ->after('electronic_invoice')
                ->constrained('cash_register_closures')
                ->onDelete('set null');
            
            $table->index('closure_id');
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
            $table->dropForeign(['closure_id']);
            $table->dropIndex(['closure_id']);
            $table->dropColumn('closure_id');
        });
    }
}





