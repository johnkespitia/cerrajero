<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryInputsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_inputs', function (Blueprint $table) {
            $table->id();
            $table->string("name",250)->unique();
            $table->boolean("active");
            $table->string("serial", 50)->unique();
            $table->unsignedBigInteger("category_id");
            $table->foreign("category_id")
                ->references('id')
                ->on('inventory_categories')
                ->onDelete('cascade');
            $table->unsignedBigInteger("measure_id");
            $table->foreign("measure_id")
                ->references('id')
                ->on('inventory_measures')
                ->onDelete('cascade');
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
        Schema::dropIfExists('inventory_inputs');
    }
}
