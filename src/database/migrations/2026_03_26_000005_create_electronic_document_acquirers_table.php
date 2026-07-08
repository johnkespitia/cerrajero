<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateElectronicDocumentAcquirersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('electronic_document_acquirers')) {
            return;
        }

        Schema::create('electronic_document_acquirers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')
                ->onDelete('set null');
            $table->string('document_type', 30);
            $table->string('document_number', 30);
            $table->unsignedTinyInteger('dv')->nullable();
            $table->string('legal_name', 200);
            $table->string('tax_regime_code', 4)->nullable();
            $table->json('tax_responsibilities')->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('city_code_dian', 5)->nullable();
            $table->string('country_code', 2)->default('CO');
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index(['document_type', 'document_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('electronic_document_acquirers');
    }
}
