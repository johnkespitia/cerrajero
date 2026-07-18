<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'apply_mode',
        'valid_from',
        'valid_until',
        'min_nights',
        'max_uses',
        'used_count',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'active' => 'boolean',
    ];

    public function isValid($checkInDate, $nights = 1)
    {
        if (!$this->active) {
            return false;
        }

        $checkDate = Carbon::parse($checkInDate);
        if (!$checkDate->between($this->valid_from, $this->valid_until)) {
            return false;
        }

        if ($this->min_nights && $nights < $this->min_nights) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(
        $basePrice,
        $nights = 1,
        $checkInDate = null,
        int $chargeableGuests = 1,
        array $guestBreakdown = []
    ): float {
        $checkDate = $checkInDate ?? Carbon::now()->format('Y-m-d');
        if (!$this->isValid($checkDate, $nights)) {
            return 0.0;
        }

        $basePrice = (float) $basePrice;
        if ($basePrice <= 0) {
            return 0.0;
        }

        $guests = max(1, $chargeableGuests);
        $applyPerGuest = ($this->apply_mode ?? 'total') === 'per_guest';

        switch ($this->type) {
            case 'percentage':
                if ($applyPerGuest && !empty($guestBreakdown)) {
                    $rate = (float) $this->value / 100;
                    $discount = ((float) ($guestBreakdown['adult_price'] ?? 0) * (int) ($guestBreakdown['adults'] ?? 0) * $rate)
                        + ((float) ($guestBreakdown['child_price'] ?? 0) * (int) ($guestBreakdown['children'] ?? 0) * $rate);

                    return min(round($discount, 2), $basePrice);
                }

                return min(round($basePrice * ((float) $this->value / 100), 2), $basePrice);
            case 'fixed':
                $amount = $applyPerGuest
                    ? (float) $this->value * $guests
                    : (float) $this->value;

                return min(round($amount, 2), $basePrice);
            default:
                return 0.0;
        }
    }

    public function incrementUsage()
    {
        $this->increment('used_count');
    }
}

