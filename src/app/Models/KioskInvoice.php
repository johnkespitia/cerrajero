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
        'cancelled_at',
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
        'payed' => 'boolean',
        'cancelled_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

    protected $appends = [
        'status',
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

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isPaid(): bool
    {
        return (bool) $this->payed && !$this->isCancelled();
    }

    public function isPending(): bool
    {
        return !(bool) $this->payed && !$this->isCancelled();
    }

    public function isRoomCredit(): bool
    {
        $this->loadMissing('payment_type');

        return (bool) ($this->payment_type?->credit)
            && $this->reservation_id !== null
            && !$this->isCancelled();
    }

    public function getStatusAttribute(): string
    {
        if ($this->isCancelled()) {
            return 'cancelled';
        }
        if ($this->isPaid()) {
            return 'paid';
        }
        return 'pending';
    }

    public function scopeNotCancelled($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopePending($query)
    {
        return $query->where('payed', false)->whereNull('cancelled_at');
    }
}
