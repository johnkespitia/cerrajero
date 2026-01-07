<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'reservation_id',
        'payed',
        'payment_code',
        'payment_type_id',
        'payed_value',
        'remain_money',
        'electronic_invoice',
        'closure_id',
        'otp_code',
        'otp_sent_at',
        'otp_verified_at',
        'otp_verified_by',
        'otp_expires_at'
    ];

    protected $casts = [
        'otp_sent_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
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

    public function closure()
    {
        return $this->belongsTo(CashRegisterClosure::class, 'closure_id');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function otpVerifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'otp_verified_by');
    }
}
