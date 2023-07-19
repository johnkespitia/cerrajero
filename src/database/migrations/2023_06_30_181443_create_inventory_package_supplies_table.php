<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryPackageSuppliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_package_supplies', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger("stock");
            $table->date("supply_date");
            $table->unsignedBigInteger('package_id');
            $table->foreign('package_id')->references('id')->on('inventory_packages');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_package_supplies');
    }
}
