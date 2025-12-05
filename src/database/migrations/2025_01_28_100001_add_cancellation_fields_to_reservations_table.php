<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCancellationFieldsToReservationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('cancellation_policy_id')->nullable()->after('cancellation_reason')
                ->constrained('cancellation_policies')->onDelete('set null');
            $table->timestamp('cancellation_deadline')->nullable()->after('cancellation_policy_id')
                ->comment('Fecha límite calculada para cancelar sin penalización');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('cancellation_deadline')
                ->comment('Monto calculado a reembolsar');
            $table->decimal('penalty_amount', 10, 2)->default(0)->after('refund_amount')
                ->comment('Monto de penalización aplicado');
            
            $table->index('cancellation_policy_id');
            $table->index('cancellation_deadline');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['cancellation_policy_id']);
            $table->dropIndex(['cancellation_policy_id']);
            $table->dropIndex(['cancellation_deadline']);
            $table->dropColumn([
                'cancellation_policy_id',
                'cancellation_deadline',
                'refund_amount',
                'penalty_amount'
            ]);
        });
    }
}

