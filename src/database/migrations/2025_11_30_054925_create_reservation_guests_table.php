<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationGuestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('document_type')->nullable(); // CC, CE, PASAPORTE, etc.
            $table->string('document_number')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('nationality')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('special_needs')->nullable(); // Necesidades especiales
            $table->boolean('is_primary_guest')->default(false); // Huésped principal
            
            // Información de Seguro Social (EPS/Aseguradora)
            $table->string('health_insurance_name')->nullable(); // Nombre de la EPS o aseguradora de salud
            $table->enum('health_insurance_type', ['national', 'international'])->nullable(); // Nacional o Internacional
            
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('document_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservation_guests');
    }
}
