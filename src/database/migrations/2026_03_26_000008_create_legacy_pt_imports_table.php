<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLegacyPtImportsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('legacy_pt_imports')) {
            return;
        }

        Schema::create('legacy_pt_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('company_fiscal_profiles')
                ->onDelete('cascade');
            $table->string('source_pt_name', 100);
            $table->string('bundle_path', 500);
            $table->string('bundle_hash_sha256', 64);
            $table->string('status', 30);
            $table->unsignedInteger('total_documents')->default(0);
            $table->unsignedInteger('consistent_count')->default(0);
            $table->unsignedInteger('inconsistent_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->json('report')->nullable();
            $table->foreignId('imported_by')->nullable()
                ->constrained('users')->onDelete('set null');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('bundle_hash_sha256');
        });
    }

    public function down()
    {
        Schema::dropIfExists('legacy_pt_imports');
    }
}
