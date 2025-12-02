<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tipo de habitación (Ej: Estándar, Suite, Familiar)
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->integer('default_capacity')->default(2); // Aforo por defecto
            $table->integer('max_capacity')->default(4); // Capacidad máxima
            $table->decimal('base_price', 10, 2)->nullable(); // Precio base del tipo (puede ser sobrescrito por habitación)
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
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
        Schema::dropIfExists('room_types');
    }
}
