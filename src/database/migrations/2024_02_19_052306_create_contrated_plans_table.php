<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContratedPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contrated_plans', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date("starting_date");
            $table->date("expiration_date");
            $table->string("short_description",200);
            $table->unsignedTinyInteger("classes");
            $table->unsignedTinyInteger("taked_classes");
            $table->unsignedBigInteger('professor_id');
            $table->foreign('professor_id')->references('id')->on('professors')->onDelete('cascade');
            $table->text('plan_extra_details');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contrated_plans');
    }
}
