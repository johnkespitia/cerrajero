<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPresentationAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_presentation_attributes', function (Blueprint $table) {
            $table->foreignId('product_presentation_id')->constrained();
            $table->foreignId('attribute_id')->constrained("product_presentations");
            $table->text("value");
            $table->primary(['product_presentation_id', 'attribute_id'], "products_presentation_attribs");
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
        Schema::dropIfExists('product_presentation_attributes');
    }
}
