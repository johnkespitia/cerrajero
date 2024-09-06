<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskInvoiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        "kiosk_invoices_id",
        "kiosk_units_id",
        "price"
    ];

    public function kiosk_invoice()
    {
        return $this->belongsTo(KioskInvoice::class, 'kiosk_invoices_id');
    }
    public function kiosk_unit()
    {
        return $this->belongsTo(KioskUnit::class, 'kiosk_units_id');
    }
}
