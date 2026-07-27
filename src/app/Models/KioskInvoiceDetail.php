<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskInvoiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'kiosk_invoices_id',
        'kiosk_units_id',
        'price',
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
    ];

    protected $casts = [
        'fiscal_tax_rate' => 'decimal:4',
        'fiscal_quantity' => 'decimal:3',
        'fiscal_unit_price' => 'decimal:2',
        'fiscal_base_amount' => 'decimal:2',
        'fiscal_tax_amount' => 'decimal:2',
        'fiscal_line_total' => 'decimal:2',
        'fiscal_snapshot' => 'array',
    ];

    public function kiosk_invoice()
    {
        return $this->belongsTo(KioskInvoice::class, 'kiosk_invoices_id');
    }

    public function kiosk_unit()
    {
        return $this->belongsTo(KioskUnit::class, 'kiosk_units_id');
    }

    /**
     * Heuristic used by KioskEmissionContextBuilder to decide between the
     * persisted snapshot and the legacy on-the-fly tax derivation. A line
     * counts as snapshot-backed when both the base amount and the unit
     * measure were captured at sale time — the two pieces the emission
     * payload cannot reconstruct safely from current product/tax state.
     */
    public function hasFiscalSnapshot(): bool
    {
        return $this->fiscal_base_amount !== null
            && $this->fiscal_unit_measure_dian !== null;
    }
}
