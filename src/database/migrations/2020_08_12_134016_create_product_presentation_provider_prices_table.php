<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPresentationProviderPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_presentation_provider_prices', function (Blueprint $table) {
            $table->id();
            $table->decimal("price_provider");
            $table->decimal("price");
            $table->boolean("status");
            $table->bigInteger("product_presentation_id", false, true);
            $table->foreign('product_presentation_id','presentation_provider_foreign')->references("id")->on('product_presentations');
            $table->foreignId('provider_id')->constrained('providers');
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
        Schema::dropIfExists('product_presentation_provider_prices');
    }
}
