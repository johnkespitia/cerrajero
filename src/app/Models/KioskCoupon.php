<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'valid_from',
        'valid_until',
        'max_uses',
        'used_count',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'active' => 'boolean',
        'max_uses' => 'integer',
        'used_count' => 'integer',
    ];

    public function isValid(?string $onDate = null): bool
    {
        if (!$this->active) {
            return false;
        }

        $date = Carbon::parse($onDate ?? Carbon::today()->toDateString())->startOfDay();
        if ($date->lt($this->valid_from->copy()->startOfDay()) || $date->gt($this->valid_until->copy()->endOfDay())) {
            return false;
        }

        if ($this->max_uses !== null && (int) $this->used_count >= (int) $this->max_uses) {
            return false;
        }

        return true;
    }

    public function discountAmountFor(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        if ($this->type === 'percentage') {
            $amount = round($subtotal * ((float) $this->value / 100), 2);
        } elseif ($this->type === 'fixed') {
            $amount = round((float) $this->value, 2);
        } else {
            return 0.0;
        }

        return min($amount, $subtotal);
    }

    public function calculateDiscount(float $subtotal): float
    {
        if (!$this->isValid()) {
            return 0.0;
        }

        return $this->discountAmountFor($subtotal);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
