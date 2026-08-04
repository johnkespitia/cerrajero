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
        'otp_expires_at',
        'coupon_code',
        'coupon_effect',
        'coupon_apply_scope',
        'coupon_discount',
        'manual_discount',
        'manual_discount_by',
        'discount_total',
    ];

    protected $casts = [
        'payed' => 'boolean',
        'cancelled_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'coupon_discount' => 'decimal:2',
        'manual_discount' => 'decimal:2',
        'discount_total' => 'decimal:2',
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

    public function manualDiscountBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'manual_discount_by');
    }

    public function subtotal(): float
    {
        if ($this->relationLoaded('details')) {
            return round((float) $this->details->sum('price'), 2);
        }

        return round((float) $this->details()->sum('price'), 2);
    }

    public function linePrices(): array
    {
        if ($this->relationLoaded('details')) {
            return $this->details->pluck('price')->map(fn ($p) => (float) $p)->all();
        }

        return $this->details()->pluck('price')->map(fn ($p) => (float) $p)->all();
    }

    public function payableTotal(): float
    {
        $subtotal = $this->subtotal();
        $couponAmount = (float) ($this->coupon_discount ?? 0);
        $manual = (float) ($this->manual_discount ?? 0);

        if (($this->coupon_effect ?? 'discount') === 'increment') {
            return max(0, round($subtotal + $couponAmount - $manual, 2));
        }

        return max(0, round($subtotal - $couponAmount - $manual, 2));
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
