<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRepairFieldsToRoomInventoryAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('room_inventory_assignments', function (Blueprint $table) {
            $table->date('repair_date')->nullable()->after('last_checked_by')->comment('Fecha de reparación');
            $table->text('repair_notes')->nullable()->after('repair_date')->comment('Notas sobre la reparación');
            $table->date('maintenance_warranty_expires_at')->nullable()->after('repair_notes')->comment('Fecha de vencimiento de garantía de mantenimiento');
            $table->foreignId('repaired_by')->nullable()->after('maintenance_warranty_expires_at')->constrained('users')->onDelete('set null')->comment('Usuario que registró la reparación');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('room_inventory_assignments', function (Blueprint $table) {
            $table->dropForeign(['repaired_by']);
            $table->dropColumn(['repair_date', 'repair_notes', 'maintenance_warranty_expires_at', 'repaired_by']);
        });
    }
}
