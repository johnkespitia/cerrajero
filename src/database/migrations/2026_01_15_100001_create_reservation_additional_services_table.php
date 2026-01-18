<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationAdditionalServicesTable extends Migration
{
    public function up()
    {
        Schema::create('reservation_additional_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('additional_service_id')->constrained()->onDelete('cascade');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('quantity', 10, 2)->default(1); // Días de servicio (per_day) o 1 (one_time)
            $table->integer('guests_count')->default(1);
            $table->decimal('total', 10, 2);
            $table->timestamps();
            $table->index('reservation_id');
            $table->index('additional_service_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reservation_additional_services');
    }
}
