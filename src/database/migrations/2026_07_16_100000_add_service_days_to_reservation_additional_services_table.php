<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddServiceDaysToReservationAdditionalServicesTable extends Migration
{
    public function up()
    {
        Schema::table('reservation_additional_services', function (Blueprint $table) {
            $table->decimal('service_days', 10, 2)->default(1)->after('quantity');
        });

        // Migrar datos existentes: quantity era días, guests_count era multiplicador por huésped
        $rows = DB::table('reservation_additional_services')
            ->join('additional_services', 'additional_services.id', '=', 'reservation_additional_services.additional_service_id')
            ->select(
                'reservation_additional_services.id',
                'reservation_additional_services.quantity as old_days',
                'reservation_additional_services.guests_count as old_guests',
                'additional_services.is_per_guest'
            )
            ->get();

        foreach ($rows as $row) {
            $itemQty = $row->is_per_guest ? max(1, (int) $row->old_guests) : 1;
            DB::table('reservation_additional_services')
                ->where('id', $row->id)
                ->update([
                    'service_days' => $row->old_days,
                    'quantity' => $itemQty,
                    'guests_count' => 1,
                ]);
        }
    }

    public function down()
    {
        Schema::table('reservation_additional_services', function (Blueprint $table) {
            $table->dropColumn('service_days');
        });
    }
}
