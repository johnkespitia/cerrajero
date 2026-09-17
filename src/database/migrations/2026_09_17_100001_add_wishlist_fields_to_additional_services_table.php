<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->boolean('preseleccionado')->default(false)->after('is_food_service')->comment('Pre-marcar en el wizard de reserva');
            $table->boolean('obligatorio')->default(false)->after('preseleccionado')->comment('No se puede desmarcar en el wizard');
            $table->unsignedInteger('orden')->default(0)->after('obligatorio')->comment('Orden de visualización');
        });
    }

    public function down(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn(['preseleccionado', 'obligatorio', 'orden']);
        });
    }
};
