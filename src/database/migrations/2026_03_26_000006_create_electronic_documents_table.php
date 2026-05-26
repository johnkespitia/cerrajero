<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateElectronicDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('electronic_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('company_fiscal_profiles')
                ->onDelete('restrict');
            $table->foreignId('resolution_id')
                ->constrained('dian_resolutions')
                ->onDelete('restrict');
            $table->string('document_type', 20);
            $table->string('reference_code', 100);
            $table->string('dian_number', 60)->nullable();
            $table->string('cufe_cude', 96)->nullable();
            $table->text('qr_url')->nullable();
            $table->string('xml_unsigned_path', 500)->nullable();
            $table->string('xml_signed_path', 500)->nullable();
            $table->string('attached_document_path', 500)->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('dian_track_id', 100)->nullable();
            $table->string('dian_zip_key', 100)->nullable();
            $table->string('software_security_code', 100)->nullable();
            $table->string('status', 40);
            $table->string('environment', 20);
            $table->string('dian_application_response_path', 500)->nullable();
            $table->boolean('dian_is_valid')->nullable();
            $table->string('dian_status_code', 20)->nullable();
            $table->json('dian_error_messages')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_taxes', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('currency_code', 3)->default('COP');
            $table->dateTime('issue_date');
            $table->string('payment_means_code', 10)->nullable();
            $table->string('payment_terms', 10)->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('acquirer_id')->nullable()
                ->constrained('electronic_document_acquirers')
                ->onDelete('set null');
            $table->unsignedBigInteger('references_document_id')->nullable();
            $table->boolean('contingency')->default(false);
            $table->string('contingency_reason', 255)->nullable();
            $table->dateTime('contingency_emitted_at')->nullable();
            $table->dateTime('contingency_synced_at')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('next_retry_at')->nullable();
            $table->string('legacy_pt_id', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'document_type', 'reference_code'],
                'electronic_documents_company_type_ref_unique'
            );
            $table->index('status');
            $table->index(['source_type', 'source_id']);
            $table->index('dian_track_id');
            $table->index('cufe_cude');
            $table->index(['company_id', 'document_type', 'dian_number'], 'electronic_documents_dian_number_idx');

            $table->foreign('references_document_id')
                ->references('id')->on('electronic_documents')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('electronic_documents', function (Blueprint $table) {
            $table->dropForeign(['references_document_id']);
        });
        Schema::dropIfExists('electronic_documents');
    }
}
