<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiagnosticClassesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('diagnostic_classes', function (Blueprint $table) {
            $table->id();
            $table->date("starting_date");
            $table->time("starting_time");
            $table->string('candidate_name',200);
            $table->string('candidate_email',200);
            $table->decimal('class_duration',4,1)->nullable();
            $table->boolean('class_closed')->default(false);
            $table->text("comments")->nullable();
            $table->decimal('hourly_fee',10,2,true)->default(0);
            $table->unsignedBigInteger('professor_id')->nullable();
            $table->foreign('professor_id')->references('id')->on('professors')->onDelete('cascade');
            $table->boolean('candidate_attended')->default(false);
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
        Schema::dropIfExists('diagnostic_classes');
    }
}
