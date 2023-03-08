<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->string("name",250)->unique();
            $table->boolean("active");
            $table->string("serial", 50)->unique();
            $table->unsignedBigInteger("input_id");
            $table->foreign("input_id")
                ->references('id')
                ->on('inventory_inputs')
                ->onDelete('cascade');
            $table->date("expiration_date");
            $table->integer("quantity", false, true);
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
        Schema::dropIfExists('inventory_batches');
    }
}
