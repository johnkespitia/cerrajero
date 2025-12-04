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

    public function calculateDiscount($basePrice, $nights = 1)
    {
        if (!$this->isValid(Carbon::now()->format('Y-m-d'), $nights)) {
            return 0;
        }

        switch ($this->type) {
            case 'percentage':
                return $basePrice * ($this->value / 100);
            case 'fixed':
                return min($this->value, $basePrice);
            case 'nights_free':
                // Para implementar más adelante
                return 0;
            default:
                return 0;
        }
    }

    public function incrementUsage()
    {
        $this->increment('used_count');
    }
}

