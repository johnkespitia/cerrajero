<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCustomersTableForCompanies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('customer_type', ['person', 'company'])->default('person')->after('id');
            $table->string('company_name')->nullable()->after('customer_type');
            $table->string('company_nit')->nullable()->after('company_name');
            $table->string('company_legal_representative')->nullable()->after('company_nit');
            $table->text('company_address')->nullable()->after('company_legal_representative');
            
            // Hacer campos de persona opcionales si es empresa
            $table->string('dni', 20)->nullable()->change();
            $table->string('name', 100)->nullable()->change();
            $table->string('last_name', 100)->nullable()->change();
            
            $table->index('customer_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_type']);
            $table->dropColumn([
                'customer_type',
                'company_name',
                'company_nit',
                'company_legal_representative',
                'company_address'
            ]);
            
            // Revertir campos a required
            $table->string('dni', 20)->nullable(false)->change();
            $table->string('name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
        });
    }
}
