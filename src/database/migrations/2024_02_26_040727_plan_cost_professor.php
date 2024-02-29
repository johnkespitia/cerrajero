<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PlanCostProfessor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contrated_plans', function (Blueprint $table) {
            $table->decimal('hourly_fee',10,2,true)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contrated_plans', function (Blueprint $table) {
            $table->dropColumn('hourly_fee');
        });
    }
}
