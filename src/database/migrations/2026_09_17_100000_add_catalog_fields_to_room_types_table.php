<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->json('incluye')->nullable()->after('features')->comment('Lista de lo que incluye la tarifa base');
            $table->json('no_incluye')->nullable()->after('incluye')->comment('Lista de lo que NO incluye la tarifa base');
            $table->string('price_unit', 30)->default('per_person_night')->after('no_incluye')->comment('per_person_night | per_unit_night');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['incluye', 'no_incluye', 'price_unit']);
        });
    }
};
