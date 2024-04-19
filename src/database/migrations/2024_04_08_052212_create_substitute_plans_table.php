<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubstitutePlans extends Migration
{
    public function up()
    {
        Schema::create('substitute_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professor_id')->nullable();
            $table->foreign('professor_id')->references('id')->on('professors')->onDelete('cascade');
            $table->unsignedBigInteger('contrated_plan_id')->nullable();
            $table->foreign('contrated_plan_id')->references('id')->on('contrated_plans')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('substitute_plans');
    }
}
