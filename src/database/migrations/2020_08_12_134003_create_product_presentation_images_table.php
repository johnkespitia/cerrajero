<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPresentationImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_presentation_images', function (Blueprint $table) {
            $table->id();
            $table->string("url_image", 200);
            $table->string("title", 200)->nullable();
            $table->boolean("status");
            $table->foreignId('product_presentation_id')->constrained('product_presentations');
            $table->engine = 'InnoDB';
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
        Schema::dropIfExists('product_presentation_images');
    }
}
