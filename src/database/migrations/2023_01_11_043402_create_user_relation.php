<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRelation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_relation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('superior_id');
            $table->unsignedBigInteger('dependency_id');
            $table->timestamps();
            $table->foreign('superior_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('dependency_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_relation');
    }
}
