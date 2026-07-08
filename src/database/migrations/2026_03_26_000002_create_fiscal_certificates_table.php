<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFiscalCertificatesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('fiscal_certificates')) {
            return;
        }

        Schema::create('fiscal_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('company_fiscal_profiles')
                ->onDelete('cascade');
            $table->string('environment', 20);
            $table->string('subject_cn', 255);
            $table->string('issuer_cn', 255);
            $table->string('serial_number', 100);
            $table->dateTime('not_before');
            $table->dateTime('not_after');
            $table->string('fingerprint_sha256', 64);
            $table->string('storage_path', 500);
            $table->string('password_secret_ref', 255);
            $table->boolean('active')->default(false);
            $table->dateTime('loaded_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'environment', 'active']);
            $table->index('not_after');
            $table->unique('fingerprint_sha256');
        });
    }

    public function down()
    {
        Schema::dropIfExists('fiscal_certificates');
    }
}
