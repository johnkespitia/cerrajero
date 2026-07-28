<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->uuid('guest_portal_token')->nullable()->unique();
            $table->string('guest_portal_otp_hash', 255)->nullable();
            $table->timestamp('guest_portal_otp_expires_at')->nullable();
            $table->unsignedTinyInteger('guest_portal_otp_attempts')->default(0);
            $table->string('guest_portal_session_hash', 255)->nullable();
            $table->timestamp('guest_portal_session_expires_at')->nullable();
            $table->timestamp('guest_portal_enabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'guest_portal_token',
                'guest_portal_otp_hash',
                'guest_portal_otp_expires_at',
                'guest_portal_otp_attempts',
                'guest_portal_session_hash',
                'guest_portal_session_expires_at',
                'guest_portal_enabled_at',
            ]);
        });
    }
};
