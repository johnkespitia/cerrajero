<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeAndCalculatorFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $table->boolean("calculator")->nullable();
            $table->boolean("credit")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn("calculator");
            $table->dropColumn("credit");
        });
    }
}
