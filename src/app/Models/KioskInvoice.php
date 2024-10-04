<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'payed',
        'payment_code',
        'payment_type_id',
        'payed_value',
        'remain_money',
        'electronic_invoice'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function payment_type()
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }
    public function details()
    {
        return $this->hasMany(KioskInvoiceDetail::class, 'kiosk_invoices_id');
    }
}
