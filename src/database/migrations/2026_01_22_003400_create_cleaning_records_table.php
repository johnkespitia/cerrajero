<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCleaningRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cleaning_records', function (Blueprint $table) {
            $table->id();
            $table->string('cleanable_type', 125)->comment('Tipo: App\Models\Room o App\Models\CommonArea');
            $table->unsignedBigInteger('cleanable_id')->comment('ID de la habitación o zona común');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade')->comment('Reserva relacionada (para limpiezas programadas automáticamente)');
            $table->foreignId('cleaned_by')->nullable()->constrained('users')->onDelete('restrict')->comment('Usuario/Empleado que realizó el aseo (NULL si está pendiente)');
            $table->date('cleaning_date')->comment('Fecha del aseo');
            $table->time('cleaning_time')->nullable()->comment('Hora del aseo');
            $table->enum('cleaning_type', ['daily', 'checkout', 'checkin', 'deep', 'maintenance'])->default('daily')->comment('Tipo de limpieza');
            $table->enum('status', ['completed', 'in_progress', 'pending'])->default('completed')->comment('Estado del aseo');
            $table->integer('duration_minutes')->nullable()->comment('Duración en minutos');
            $table->text('observations')->nullable()->comment('Observaciones y novedades');
            $table->text('issues_found')->nullable()->comment('Problemas encontrados durante el aseo');
            $table->text('items_missing')->nullable()->comment('Artículos faltantes detectados');
            $table->integer('quality_score')->nullable()->comment('Calificación de calidad (1-10)');
            $table->boolean('supervisor_checked')->default(false)->comment('Verificado por supervisor');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null')->comment('Supervisor que verificó');
            $table->text('supervisor_notes')->nullable()->comment('Notas del supervisor');
            $table->date('next_cleaning_due')->nullable()->comment('Próxima limpieza programada');
            $table->timestamps();
            
            $table->index(['cleanable_type', 'cleanable_id'], 'cleaning_records_cleanable_index');
            $table->index('reservation_id');
            $table->index('cleaned_by');
            $table->index('cleaning_date');
            $table->index('status');
            $table->index('supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cleaning_records');
    }
}
