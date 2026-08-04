<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskCoupon extends Model
{
    use HasFactory;

    public const EFFECT_DISCOUNT = 'discount';
    public const EFFECT_INCREMENT = 'increment';
    public const SCOPE_CART = 'cart';
    public const SCOPE_ITEM = 'item';

    protected $fillable = [
        'code',
        'name',
        'type',
        'effect',
        'apply_scope',
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

    public function isIncrement(): bool
    {
        return ($this->effect ?? self::EFFECT_DISCOUNT) === self::EFFECT_INCREMENT;
    }

    public function isItemScope(): bool
    {
        return ($this->apply_scope ?? self::SCOPE_CART) === self::SCOPE_ITEM;
    }

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

    /**
     * Monto absoluto del ajuste (descuento o incremento) sobre una base.
     * Para descuento se capea a la base; para incremento no.
     */
    public function amountForBase(float $base): float
    {
        if ($base < 0) {
            return 0.0;
        }

        if ($this->type === 'percentage') {
            $amount = round($base * ((float) $this->value / 100), 2);
        } elseif ($this->type === 'fixed') {
            $amount = round((float) $this->value, 2);
        } else {
            return 0.0;
        }

        if ($this->isIncrement()) {
            return max(0.0, $amount);
        }

        return min(max(0.0, $amount), $base);
    }

    /**
     * @param  array<int, float>  $linePrices  precios por unidad/línea (para scope item)
     */
    public function calculateAdjustment(float $subtotal, array $linePrices = []): float
    {
        if (!$this->isValid()) {
            return 0.0;
        }

        if ($this->isItemScope()) {
            if (empty($linePrices)) {
                return 0.0;
            }

            $total = 0.0;
            foreach ($linePrices as $price) {
                $total += $this->amountForBase((float) $price);
            }

            return round($total, 2);
        }

        return $this->amountForBase($subtotal);
    }

    /** @deprecated usar calculateAdjustment */
    public function discountAmountFor(float $subtotal): float
    {
        return $this->amountForBase($subtotal);
    }

    /** @deprecated usar calculateAdjustment */
    public function calculateDiscount(float $subtotal): float
    {
        return $this->calculateAdjustment($subtotal);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
