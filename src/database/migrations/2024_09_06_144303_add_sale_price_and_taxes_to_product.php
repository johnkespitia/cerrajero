<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalePriceAndTaxesToProduct extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kiosk_products', function (Blueprint $table) {
            $table->unsignedBigInteger("tax_id")->nullable();
            $table->foreign('tax_id')->references('id')->on('taxes');
            $table->decimal('sale_price', 10,2)->min(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kiosk_products', function (Blueprint $table) {
            $table->dropColumn('sale_price');
            $table->dropForeign(['tax_id']);
            $table->dropColumn('tax_id');
        });
    }
}
