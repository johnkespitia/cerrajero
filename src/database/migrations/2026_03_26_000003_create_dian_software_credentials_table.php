<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDianSoftwareCredentialsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('dian_software_credentials')) {
            return;
        }

        Schema::create('dian_software_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('company_fiscal_profiles')
                ->onDelete('cascade');
            $table->string('environment', 20);
            $table->uuid('software_id');
            $table->string('software_pin_secret_ref', 255);
            $table->uuid('test_set_id')->nullable();
            $table->string('production_url', 500);
            $table->string('habilitacion_url', 500);
            $table->timestamps();

            $table->unique(['company_id', 'environment']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dian_software_credentials');
    }
}
