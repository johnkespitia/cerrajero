<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->string('number')->unique()->nullable(); // Número o nombre de la habitación
            $table->string('name')->nullable(); // Nombre alternativo si no hay número
            $table->integer('capacity')->default(2); // Aforo de la habitación
            $table->integer('max_capacity')->default(4); // Capacidad máxima
            $table->integer('extra_bed_capacity')->default(0);
            $table->decimal('room_price', 10, 2); // Valor de la habitación
            $table->decimal('extra_person_price', 10, 2)->default(0);
            $table->decimal('extra_bed_price', 10, 2)->default(0);
            $table->text('description')->nullable(); // Descripción de la habitación
            $table->enum('status', ['available', 'occupied', 'maintenance', 'out_of_order'])->default('available');
            $table->json('amenities')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('room_type_id');
            $table->index('status');
            $table->index('active');
            // Índice compuesto para búsqueda por tipo y disponibilidad
            $table->index(['room_type_id', 'status', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rooms');
    }
}
