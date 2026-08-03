<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->boolean('is_infant')->default(false)->after('is_primary_guest');
            $table->boolean('is_child')->default(false)->after('is_infant');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->dropColumn(['is_infant', 'is_child']);
        });
    }
};
