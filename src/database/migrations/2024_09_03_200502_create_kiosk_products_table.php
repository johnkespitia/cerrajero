<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKioskProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kiosk_products', function (Blueprint $table) {
            $table->id();
            $table->string("name", 100)->unique();
            $table->string("code", 20)->unique();
            $table->string("image",200)->nullable();
            $table->text("description")->nullable();
            $table->unsignedBigInteger("category_id");
            $table->boolean("active")->default(false);
            $table->foreign('category_id')->references('id')->on('kiosk_categories');
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
        Schema::dropIfExists('kiosk_products');
    }
}
