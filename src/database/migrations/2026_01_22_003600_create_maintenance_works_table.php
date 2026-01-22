<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaintenanceWorksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('maintenance_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->nullable()->constrained('maintenance_requests')->onDelete('set null')->comment('Solicitud relacionada (opcional)');
            $table->string('maintainable_type', 125)->comment('Tipo: App\Models\Room o App\Models\CommonArea');
            $table->unsignedBigInteger('maintainable_id')->comment('ID de la habitación o zona común');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null')->comment('Proveedor que realizó el trabajo');
            $table->enum('work_type', ['repair', 'replacement', 'installation', 'maintenance', 'inspection'])->default('repair')->comment('Tipo de trabajo');
            $table->date('work_date')->comment('Fecha del trabajo');
            $table->time('work_start_time')->nullable()->comment('Hora de inicio');
            $table->time('work_end_time')->nullable()->comment('Hora de finalización');
            $table->text('description')->comment('Descripción del trabajo realizado');
            $table->text('materials_used')->nullable()->comment('Materiales utilizados');
            $table->decimal('labor_cost', 10, 2)->default(0.00)->comment('Costo de mano de obra');
            $table->decimal('materials_cost', 10, 2)->default(0.00)->comment('Costo de materiales');
            $table->decimal('total_cost', 10, 2)->default(0.00)->comment('Costo total');
            $table->date('warranty_start_date')->nullable()->comment('Fecha de inicio de garantía');
            $table->date('warranty_end_date')->nullable()->comment('Fecha de fin de garantía');
            $table->integer('warranty_months')->nullable()->comment('Meses de garantía');
            $table->text('warranty_terms')->nullable()->comment('Términos de la garantía');
            $table->string('invoice_number', 100)->nullable()->comment('Número de factura');
            $table->date('invoice_date')->nullable()->comment('Fecha de factura');
            $table->string('invoice_file_url', 500)->nullable()->comment('URL del archivo de factura');
            $table->enum('status', ['completed', 'in_progress', 'cancelled'])->default('completed')->comment('Estado del trabajo');
            $table->integer('quality_rating')->nullable()->comment('Calificación del trabajo (1-10)');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que registró el trabajo');
            $table->timestamps();
            
            $table->index('maintenance_request_id');
            $table->index(['maintainable_type', 'maintainable_id'], 'maintenance_works_maintainable_index');
            $table->index('supplier_id');
            $table->index('work_date');
            $table->index('status');
            $table->index('warranty_end_date');
            $table->index('performed_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('maintenance_works');
    }
}
