<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdditionalServicesTable extends Migration
{
    public function up()
    {
        Schema::create('additional_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0); // Por día (billing_type=per_day) o monto fijo (billing_type=one_time)
            $table->enum('billing_type', ['per_day', 'one_time'])->default('per_day');
            $table->enum('applies_to', ['room', 'day_pass', 'both'])->default('both');
            $table->boolean('is_per_guest')->default(true); // Si el precio se multiplica por cantidad de huéspedes
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index('status');
            $table->index('applies_to');
            $table->index('billing_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('additional_services');
    }
}
