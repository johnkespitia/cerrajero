<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250)->comment('Nombre del proveedor');
            $table->string('contact_name', 125)->nullable()->comment('Nombre de contacto');
            $table->string('email', 200)->nullable()->comment('Email de contacto');
            $table->string('phone', 20)->nullable()->comment('Teléfono');
            $table->text('address')->nullable()->comment('Dirección');
            $table->string('tax_id', 50)->nullable()->comment('NIT o identificación tributaria');
            $table->string('service_type', 100)->nullable()->comment('Tipo de servicio (plomería, electricidad, carpintería, etc.)');
            $table->decimal('rating', 3, 2)->nullable()->comment('Calificación del proveedor (1-5)');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->boolean('active')->default(true)->comment('Activo/Inactivo');
            $table->timestamps();
            
            $table->index('active');
            $table->index('service_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
}
