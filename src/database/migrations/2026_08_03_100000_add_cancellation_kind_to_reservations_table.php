<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCancellationKindToReservationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('cancellation_kind', 32)
                ->default('customer')
                ->after('cancellation_reason')
                ->comment('customer = cancelación de cliente; staff_error = anulación por error de captura');
            $table->index('cancellation_kind');
        });

        // Backfill: cancelaciones existentes se tratan como cancelación de cliente
        DB::table('reservations')
            ->where('status', 'cancelled')
            ->whereNull('cancellation_kind')
            ->update(['cancellation_kind' => 'customer']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['cancellation_kind']);
            $table->dropColumn('cancellation_kind');
        });
    }
}
