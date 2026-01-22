<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaintenanceRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('maintainable_type', 125)->comment('Tipo: App\Models\Room o App\Models\CommonArea');
            $table->unsignedBigInteger('maintainable_id')->comment('ID de la habitación o zona común');
            $table->foreignId('reported_by')->constrained('users')->onDelete('restrict')->comment('Usuario que reporta el problema');
            $table->date('reported_date')->comment('Fecha del reporte');
            $table->time('reported_time')->nullable()->comment('Hora del reporte');
            $table->enum('issue_type', ['damage', 'repair', 'preventive', 'inspection', 'other'])->default('damage')->comment('Tipo de problema');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->comment('Prioridad');
            $table->string('title', 250)->comment('Título del problema');
            $table->text('description')->comment('Descripción detallada del problema');
            $table->string('location_detail', 250)->nullable()->comment('Ubicación específica (en la habitación o zona común)');
            $table->foreignId('related_inventory_item_id')->nullable()->constrained('room_inventory_items')->onDelete('set null')->comment('Artículo del inventario relacionado (opcional)');
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled', 'on_hold'])->default('pending')->comment('Estado de la solicitud');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario/Proveedor asignado');
            $table->date('assigned_date')->nullable()->comment('Fecha de asignación');
            $table->decimal('estimated_cost', 10, 2)->nullable()->comment('Costo estimado');
            $table->decimal('estimated_duration_hours', 5, 2)->nullable()->comment('Duración estimada en horas');
            $table->date('completed_date')->nullable()->comment('Fecha de completación');
            $table->time('completed_time')->nullable()->comment('Hora de completación');
            $table->text('resolution_notes')->nullable()->comment('Notas de resolución');
            $table->timestamps();
            
            $table->index(['maintainable_type', 'maintainable_id'], 'maintenance_requests_maintainable_index');
            $table->index('reported_by');
            $table->index('reported_date');
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            $table->index('related_inventory_item_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('maintenance_requests');
    }
}
