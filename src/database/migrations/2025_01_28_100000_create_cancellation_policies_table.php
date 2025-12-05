<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCancellationPoliciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('policy_type', ['free', 'partial', 'non_refundable'])->default('free');
            $table->integer('cancellation_days_before')->nullable()->comment('Días antes del check-in para cancelar sin penalización');
            $table->decimal('penalty_percentage', 5, 2)->default(0)->comment('Porcentaje de penalización (0-100)');
            $table->decimal('penalty_fee', 10, 2)->default(0)->comment('Tarifa fija de penalización');
            $table->enum('apply_to', ['all', 'room_type', 'reservation_type'])->default('all');
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->onDelete('cascade');
            $table->enum('reservation_type', ['room', 'day_pass'])->nullable();
            $table->boolean('is_default')->default(false)->comment('Política por defecto');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('room_type_id');
            $table->index('reservation_type');
            $table->index('is_default');
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
        Schema::dropIfExists('cancellation_policies');
    }
}

