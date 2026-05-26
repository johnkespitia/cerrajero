<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiscalFieldsToReservationsTable extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('electronic_document_id')->nullable()
                ->constrained('electronic_documents')
                ->onDelete('set null');
            $table->string('fiscal_payment_means_code', 10)->nullable();
            $table->string('fiscal_payment_terms', 10)->nullable();
            $table->date('fiscal_due_date')->nullable();

            $table->index('electronic_document_id', 'reservations_edoc_idx');
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['electronic_document_id']);
            $table->dropIndex('reservations_edoc_idx');
            $table->dropColumn([
                'electronic_document_id',
                'fiscal_payment_means_code',
                'fiscal_payment_terms',
                'fiscal_due_date',
            ]);
        });
    }
}
