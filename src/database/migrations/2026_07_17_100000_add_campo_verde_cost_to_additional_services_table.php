<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->decimal('campo_verde_cost', 10, 2)
                ->default(0)
                ->after('price')
                ->comment('Costo interno Campo Verde para reportes de gastos; no se muestra al cliente');
        });
    }

    public function down(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('campo_verde_cost');
        });
    }
};
