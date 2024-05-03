<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlanClassDuration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contrated_plans', function (Blueprint $table) {
            $table->decimal('classes', 10,2)->change();
            $table->decimal('taked_classes',10,2)->change();
            $table->decimal('estimated_class_duration', 4,1)->nullable();
        });
        Schema::table('imparted_classes', function (Blueprint $table) {
            $table->decimal('class_duration',4,1)->nullable();
            $table->boolean('class_closed')->default(false);
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
