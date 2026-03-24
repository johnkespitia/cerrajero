<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AllowNullPaymentTypeIdInReservationPayments extends Migration
{
    /**
     * Run the migrations.
     * Permite NULL en payment_type_id para cargos a habitación (ej. consumo restaurante)
     * que no tienen método de pago inmediato.
     *
     * @return void
     */
    public function up()
    {
        // SQLite no soporta dropForeign ni ALTER ... MODIFY sobre tablas existentes.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
        });

        DB::statement('ALTER TABLE reservation_payments MODIFY payment_type_id BIGINT UNSIGNED NULL');

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->foreign('payment_type_id')->references('id')->on('payment_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // SQLite no soporta dropForeign ni ALTER ... MODIFY sobre tablas existentes.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
        });

        DB::statement('ALTER TABLE reservation_payments MODIFY payment_type_id BIGINT UNSIGNED NOT NULL');

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->foreign('payment_type_id')->references('id')->on('payment_types')->onDelete('restrict');
        });
    }
}
