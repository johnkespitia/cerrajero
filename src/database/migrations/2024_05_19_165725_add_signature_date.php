<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSignatureDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('professor_invoices', function (Blueprint $table) {
            $table->date("sent_date")->nullable();
            $table->date("approved_date")->nullable();
            $table->date("payed_date")->nullable();
            $table->string('signature_img',200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
