<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDianResolutionsTable extends Migration
{
    public function up()
    {
        Schema::create('dian_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('company_fiscal_profiles')
                ->onDelete('cascade');
            $table->string('environment', 20);
            $table->string('document_type', 20);
            $table->string('prefix', 8);
            $table->string('resolution_number', 50);
            $table->date('resolution_date');
            $table->unsignedBigInteger('from_number');
            $table->unsignedBigInteger('to_number');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('technical_key', 100)->nullable();
            $table->unsignedBigInteger('current_number')->default(0);
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(
                ['company_id', 'environment', 'document_type', 'prefix', 'resolution_number'],
                'dian_resolutions_unique_per_company'
            );
            $table->index(['company_id', 'environment', 'document_type', 'active'], 'dian_resolutions_active_idx');
            $table->index('valid_to');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dian_resolutions');
    }
}
