<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateReservationSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insertar configuraciones por defecto
        DB::table('reservation_settings')->insert([
            ['key' => 'max_advance_days', 'value' => '365', 'description' => 'Días máximos de anticipación para reservar', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'min_stay_nights', 'value' => '1', 'description' => 'Noches mínimas de estadía', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_stay_nights', 'value' => '30', 'description' => 'Noches máximas de estadía', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_reservations_per_customer', 'value' => '5', 'description' => 'Reservas simultáneas máximas por cliente', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'check_in_time', 'value' => '15:00', 'description' => 'Hora de check-in por defecto', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'check_out_time', 'value' => '12:00', 'description' => 'Hora de check-out por defecto', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservation_settings');
    }
}

