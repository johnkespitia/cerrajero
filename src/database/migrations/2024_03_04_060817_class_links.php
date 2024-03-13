<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ClassLinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('class_links', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('links_id');
            $table->unsignedBigInteger('imparted_class_id');
            $table->foreign('imparted_class_id')->references('id')->on('imparted_classes')->onDelete('cascade');
            $table->foreign('links_id')->references('id')->on('links')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('class_links');
    }
}
