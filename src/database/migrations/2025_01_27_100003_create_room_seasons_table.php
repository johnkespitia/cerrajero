<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomSeasonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Alta, Baja, Media, etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price_multiplier', 5, 2)->default(1.00); // 1.5 = 50% más caro
            $table->decimal('fixed_price', 10, 2)->nullable(); // O precio fijo (tiene prioridad sobre multiplier)
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['room_type_id', 'start_date', 'end_date']);
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_seasons');
    }
}

