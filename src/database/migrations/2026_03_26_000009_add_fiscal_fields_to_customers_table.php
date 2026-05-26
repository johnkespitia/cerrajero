<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiscalFieldsToCustomersTable extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('fiscal_document_type', 30)->nullable()->after('phone_number');
            $table->string('fiscal_document_number', 30)->nullable()->after('fiscal_document_type');
            $table->unsignedTinyInteger('fiscal_dv')->nullable()->after('fiscal_document_number');
            $table->string('fiscal_legal_name', 200)->nullable()->after('fiscal_dv');
            $table->string('fiscal_tax_regime_code', 4)->nullable()->after('fiscal_legal_name');
            $table->json('fiscal_tax_responsibilities')->nullable()->after('fiscal_tax_regime_code');
            $table->string('fiscal_address_line', 255)->nullable()->after('fiscal_tax_responsibilities');
            $table->string('fiscal_city_code_dian', 5)->nullable()->after('fiscal_address_line');
            $table->string('fiscal_country_code', 2)->nullable()->after('fiscal_city_code_dian');
            $table->string('fiscal_email', 200)->nullable()->after('fiscal_country_code');
            $table->string('fiscal_phone', 30)->nullable()->after('fiscal_email');

            $table->index(['fiscal_document_type', 'fiscal_document_number'], 'customers_fiscal_doc_idx');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_fiscal_doc_idx');
            $table->dropColumn([
                'fiscal_document_type',
                'fiscal_document_number',
                'fiscal_dv',
                'fiscal_legal_name',
                'fiscal_tax_regime_code',
                'fiscal_tax_responsibilities',
                'fiscal_address_line',
                'fiscal_city_code_dian',
                'fiscal_country_code',
                'fiscal_email',
                'fiscal_phone',
            ]);
        });
    }
}
