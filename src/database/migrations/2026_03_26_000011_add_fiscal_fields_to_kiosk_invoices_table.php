<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiscalFieldsToKioskInvoicesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('kiosk_invoices') || Schema::hasColumn('kiosk_invoices', 'electronic_document_id')) {
            return;
        }

        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->foreignId('electronic_document_id')->nullable()
                ->after('closure_id')
                ->constrained('electronic_documents')
                ->onDelete('set null');
            $table->foreignId('acquirer_id')->nullable()
                ->after('electronic_document_id')
                ->constrained('electronic_document_acquirers')
                ->onDelete('set null');

            $table->index('electronic_document_id', 'kiosk_invoices_edoc_idx');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('kiosk_invoices') || ! Schema::hasColumn('kiosk_invoices', 'electronic_document_id')) {
            return;
        }

        Schema::table('kiosk_invoices', function (Blueprint $table) {
            $table->dropForeign(['electronic_document_id']);
            $table->dropForeign(['acquirer_id']);
            $table->dropIndex('kiosk_invoices_edoc_idx');
            $table->dropColumn(['electronic_document_id', 'acquirer_id']);
        });
    }
}
