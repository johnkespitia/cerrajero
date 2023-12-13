<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarSlotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_slots', function (Blueprint $table) {
            $table->id();
            $table->date('date_slot');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('available')->default(true);
            $table->foreignId('professor_id')->constrained();
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
        Schema::dropIfExists('calendar_slots');
    }
}
