<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyFiscalProfilesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('company_fiscal_profiles')) {
            return;
        }

        Schema::create('company_fiscal_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->string('nit', 20);
            $table->unsignedTinyInteger('dv');
            $table->string('tax_regime_code', 4);
            $table->json('tax_responsibilities')->nullable();
            $table->string('economic_activity_code', 4)->nullable();
            $table->string('address_line', 255);
            $table->string('city_code_dian', 5);
            $table->string('country_code', 2)->default('CO');
            $table->string('email', 200);
            $table->string('phone', 30)->nullable();
            $table->string('environment', 20);
            $table->dateTime('migration_cutoff_date')->nullable();
            $table->string('legacy_pt_name', 100)->nullable();
            $table->boolean('active')->default(false);
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['nit', 'environment']);
            $table->index('active');
            $table->index('environment');
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_fiscal_profiles');
    }
}
