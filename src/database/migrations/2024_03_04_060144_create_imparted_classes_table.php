<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImpartedClassesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('imparted_classes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('contrated_plan_id');
            $table->date('scheduled_class');
            $table->time('class_time');
            $table->boolean('professor_atendance');
            $table->text("comments");
            $table->foreign('contrated_plan_id')->references('id')->on('contrated_plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('imparted_classes');
    }
}
