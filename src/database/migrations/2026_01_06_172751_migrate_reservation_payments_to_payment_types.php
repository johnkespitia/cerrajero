<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentType;

class MigrateReservationPaymentsToPaymentTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Crear los PaymentType básicos si no existen
        $paymentTypesMap = [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'check' => 'Cheque',
            'other' => 'Otro'
        ];

        $paymentTypeIds = [];
        foreach ($paymentTypesMap as $enumValue => $name) {
            $paymentType = PaymentType::firstOrCreate(
                ['name' => $name],
                [
                    'active' => true,
                    'credit' => false,
                    'calculator' => true
                ]
            );
            $paymentTypeIds[$enumValue] = $paymentType->id;
        }

        // 2. Agregar campo payment_type_id a reservation_payments
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_type_id')->nullable()->after('reservation_id');
        });

        // 3. Migrar datos existentes del ENUM a payment_type_id
        foreach ($paymentTypeIds as $enumValue => $paymentTypeId) {
            DB::table('reservation_payments')
                ->where('payment_method', $enumValue)
                ->update(['payment_type_id' => $paymentTypeId]);
        }

        // 4. Hacer el campo payment_type_id obligatorio (después de migrar)
        DB::statement('ALTER TABLE reservation_payments MODIFY payment_type_id BIGINT UNSIGNED NOT NULL');

        // 5. Agregar foreign key después de que el campo sea NOT NULL
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->foreign('payment_type_id')->references('id')->on('payment_types')->onDelete('restrict');
        });

        // 6. Eliminar el campo payment_method (ENUM)
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 1. Agregar de nuevo el campo payment_method (ENUM)
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check', 'other'])->default('cash')->after('amount');
        });

        // 2. Migrar datos de payment_type_id a payment_method
        $paymentTypesMap = [
            'Efectivo' => 'cash',
            'Tarjeta' => 'card',
            'Transferencia' => 'transfer',
            'Cheque' => 'check',
            'Otro' => 'other'
        ];

        foreach ($paymentTypesMap as $name => $enumValue) {
            $paymentType = PaymentType::where('name', $name)->first();
            if ($paymentType) {
                DB::table('reservation_payments')
                    ->where('payment_type_id', $paymentType->id)
                    ->update(['payment_method' => $enumValue]);
            }
        }

        // 3. Eliminar foreign key y campo payment_type_id
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_type_id']);
            $table->dropColumn('payment_type_id');
        });
    }
}
