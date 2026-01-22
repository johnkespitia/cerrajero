<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCleaningScheduleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cleaning_schedule', function (Blueprint $table) {
            $table->id();
            $table->string('cleanable_type', 125)->comment('Tipo: App\Models\Room o App\Models\CommonArea');
            $table->unsignedBigInteger('cleanable_id')->comment('ID de la habitación o zona común');
            $table->enum('cleaning_type', ['daily', 'checkout', 'deep', 'maintenance'])->default('daily')->comment('Tipo de limpieza');
            $table->integer('frequency_days')->default(1)->comment('Frecuencia en días');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario asignado por defecto');
            $table->time('time_preference')->nullable()->comment('Hora preferida');
            $table->tinyInteger('day_of_week')->nullable()->comment('Día de la semana (1=Lunes, 7=Domingo, NULL=todos)');
            $table->boolean('active')->default(true)->comment('Programación activa');
            $table->date('last_cleaned_date')->nullable()->comment('Última fecha de limpieza');
            $table->date('next_cleaning_date')->nullable()->comment('Próxima fecha programada');
            $table->text('notes')->nullable()->comment('Notas sobre la programación');
            $table->timestamps();
            
            $table->index(['cleanable_type', 'cleanable_id'], 'cleaning_schedule_cleanable_index');
            $table->index('assigned_to');
            $table->index('active');
            $table->index('next_cleaning_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cleaning_schedule');
    }
}
