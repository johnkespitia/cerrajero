<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateElectronicDocumentEventsTable extends Migration
{
    public function up()
    {
        Schema::create('electronic_document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_document_id')
                ->constrained('electronic_documents')
                ->onDelete('cascade');
            $table->string('event_type', 60);
            $table->json('payload')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->string('actor', 60);
            $table->uuid('correlation_id');
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['electronic_document_id', 'event_type'], 'edev_doc_type_idx');
            $table->index('correlation_id');
            $table->index('occurred_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('electronic_document_events');
    }
}
