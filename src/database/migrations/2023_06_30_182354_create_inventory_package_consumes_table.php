<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryPackageConsumesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_package_consumes', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger("stock_consumed");
                $table->date("consumed_date");
                $table->boolean("spoilt");
                $table->unsignedBigInteger('package_id');
                $table->foreign('package_id')->references('id')->on('inventory_packages');
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->foreign('batch_id')->references('id')->on('produced_batches');
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
        Schema::dropIfExists('inventory_package_consumes');
    }
}
