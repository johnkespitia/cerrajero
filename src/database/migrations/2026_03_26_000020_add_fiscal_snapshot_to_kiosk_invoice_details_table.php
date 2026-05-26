<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist a per-line fiscal snapshot at the moment of the kiosk sale.
 *
 * Without this snapshot, the electronic emission flow always recomputes
 * `tax_rate` and `unit_measure` from the live KioskProduct/Tax rows. Once
 * a sale closes, mutating those upstream rows would change the historical
 * fiscal totals — unacceptable for a DIAN audit trail.
 *
 * All columns are nullable so legacy rows continue to load via Eloquent.
 * KioskEmissionContextBuilder treats a missing snapshot as a fallback to
 * the previous behaviour.
 */
class AddFiscalSnapshotToKioskInvoiceDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('kiosk_invoice_details', function (Blueprint $table) {
            $table->unsignedBigInteger('fiscal_tax_id')->nullable()->after('price');
            $table->string('fiscal_tax_code_dian', 4)->nullable()->after('fiscal_tax_id');
            $table->string('fiscal_tax_name', 60)->nullable()->after('fiscal_tax_code_dian');
            $table->decimal('fiscal_tax_rate', 8, 4)->nullable()->after('fiscal_tax_name');
            $table->string('fiscal_unit_measure_dian', 12)->nullable()->after('fiscal_tax_rate');
            $table->decimal('fiscal_quantity', 12, 3)->nullable()->after('fiscal_unit_measure_dian');
            $table->decimal('fiscal_unit_price', 14, 2)->nullable()->after('fiscal_quantity');
            $table->decimal('fiscal_base_amount', 14, 2)->nullable()->after('fiscal_unit_price');
            $table->decimal('fiscal_tax_amount', 14, 2)->nullable()->after('fiscal_base_amount');
            $table->decimal('fiscal_line_total', 14, 2)->nullable()->after('fiscal_tax_amount');
            $table->json('fiscal_snapshot')->nullable()->after('fiscal_line_total');

            $table->index('fiscal_tax_id', 'kiosk_invoice_details_fiscal_tax_idx');
        });
    }

    public function down()
    {
        Schema::table('kiosk_invoice_details', function (Blueprint $table) {
            $table->dropIndex('kiosk_invoice_details_fiscal_tax_idx');
            $table->dropColumn([
                'fiscal_tax_id',
                'fiscal_tax_code_dian',
                'fiscal_tax_name',
                'fiscal_tax_rate',
                'fiscal_unit_measure_dian',
                'fiscal_quantity',
                'fiscal_unit_price',
                'fiscal_base_amount',
                'fiscal_tax_amount',
                'fiscal_line_total',
                'fiscal_snapshot',
            ]);
        });
    }
}
