<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDianEventsTable extends Migration
{
    public function up()
    {
        Schema::create('dian_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_document_id')
                ->constrained('electronic_documents')
                ->onDelete('cascade');
            // Codigo del evento RADIAN: 030, 031, 032, 033, 034.
            $table->string('event_code', 3);
            // Estado del evento RADIAN: built, signed, sent_to_dian,
            // dian_accepted, dian_rejected, error.
            $table->string('status', 30);
            $table->string('cude', 96)->nullable();
            $table->string('xml_signed_path', 255)->nullable();
            $table->string('dian_track_id', 64)->nullable();
            $table->string('dian_status_code', 16)->nullable();
            $table->boolean('dian_is_valid')->nullable();
            $table->json('dian_error_messages')->nullable();
            $table->string('dian_application_response_path', 255)->nullable();
            $table->string('actor', 60);
            $table->uuid('correlation_id');
            $table->dateTime('emitted_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['electronic_document_id', 'event_code'], 'dian_events_doc_code_idx');
            $table->index('dian_track_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dian_events');
    }
}
