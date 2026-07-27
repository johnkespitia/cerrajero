<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WidenCodeComplementOnKioskUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * code_complement se genera como "{base}-{index}". Con base de 8 chars
     * y quantity >= 11, el sufijo "-10" supera VARCHAR(10).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kiosk_units', function (Blueprint $table) {
            $table->string('code_complement', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kiosk_units', function (Blueprint $table) {
            $table->string('code_complement', 10)->change();
        });
    }
}
