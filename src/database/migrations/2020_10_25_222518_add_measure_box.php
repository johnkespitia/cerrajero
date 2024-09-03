<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMeasureBox extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_presentation_provider_prices', function (Blueprint $table) {
            $table->unsignedSmallInteger('box_width')->nullable();
            $table->unsignedSmallInteger('box_height')->nullable();
            $table->unsignedSmallInteger('box_length')->nullable();
            $table->unsignedSmallInteger('box_weight')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
