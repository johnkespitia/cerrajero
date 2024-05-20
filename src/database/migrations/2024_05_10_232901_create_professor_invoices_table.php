<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessorInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('professor_invoices', function (Blueprint $table) {
            $table->id();
            $table->date("generation_time");
            $table->date("start_date");
            $table->date("end_date");
            $table->decimal('total_time',10,2);
            $table->decimal('total_value',10,2);
            $table->unsignedBigInteger('professor_id');
            $table->foreign('professor_id')->references('id')->on('professors')->onDelete('cascade');
            $table->text('comments')->nullable();
            $table->boolean("sent")->default(false);
            $table->boolean("approved")->default(false);
            $table->boolean("payed")->default(false);
            $table->timestamps();
        });

        Schema::table('imparted_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('professor_invoice_id')->nullable();
            $table->foreign('professor_invoice_id')->references('id')->on('professor_invoices')->onDelete('cascade');
        });

        Schema::table('diagnostic_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('professor_invoice_id')->nullable();
            $table->foreign('professor_invoice_id')->references('id')->on('professor_invoices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::dropIfExists('professor_invoices');
    }
}
