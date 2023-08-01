<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyPackagesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inventory_package_supplies', function (Blueprint $table) {
            $table->unsignedInteger('stock')->change();
        });
        Schema::table('inventory_package_consumes', function (Blueprint $table) {
            $table->unsignedInteger('stock_consumed')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
