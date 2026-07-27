<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_types') || Schema::hasColumn('room_types', 'image_url')) {
            return;
        }

        Schema::table('room_types', function (Blueprint $table) {
            $table->string('image_url', 500)->nullable()->after('description');
            $table->json('gallery')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_types') || ! Schema::hasColumn('room_types', 'image_url')) {
            return;
        }

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'gallery']);
        });
    }
};
