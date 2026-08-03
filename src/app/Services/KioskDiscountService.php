<?php

namespace App\Services;

use App\Models\KioskCoupon;
use App\Models\KioskInvoice;
use Illuminate\Validation\ValidationException;

class KioskDiscountService
{
    public function resolve(float $subtotal, ?string $couponCode, $manualDiscountInput): array
    {
        $subtotal = max(0, round((float) $subtotal, 2));
        $couponCode = $couponCode !== null ? strtoupper(trim($couponCode)) : null;
        if ($couponCode === '') {
            $couponCode = null;
        }

        $manualRequested = round(max(0, (float) ($manualDiscountInput ?? 0)), 2);

        $coupon = null;
        $couponDiscount = 0.0;

        if ($couponCode !== null) {
            $coupon = KioskCoupon::where('code', $couponCode)->first();
            if (!$coupon || !$coupon->isValid()) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['El cupón no es válido, está vencido o agotó sus usos.'],
                ]);
            }
            $couponDiscount = $coupon->calculateDiscount($subtotal);
        }

        $remainder = max(0, round($subtotal - $couponDiscount, 2));
        $manualDiscount = min($manualRequested, $remainder);
        $discountTotal = round($couponDiscount + $manualDiscount, 2);
        $payable = max(0, round($subtotal - $discountTotal, 2));

        return [
            'coupon_code' => $coupon?->code,
            'coupon_discount' => $couponDiscount,
            'manual_discount' => $manualDiscount,
            'discount_total' => $discountTotal,
            'payable' => $payable,
            'coupon' => $coupon,
        ];
    }

    public function applyToInvoice(
        KioskInvoice $invoice,
        array $resolved,
        ?int $userId,
        bool $incrementUsage = true
    ): void {
        $invoice->coupon_code = $resolved['coupon_code'];
        $invoice->coupon_discount = $resolved['coupon_discount'];
        $invoice->manual_discount = $resolved['manual_discount'];
        $invoice->discount_total = $resolved['discount_total'];
        $invoice->manual_discount_by = ((float) $resolved['manual_discount'] > 0) ? $userId : null;
        $invoice->save();

        if ($incrementUsage && $resolved['coupon'] instanceof KioskCoupon) {
            $resolved['coupon']->incrementUsage();
        }
    }

    public function recalculateExisting(float $subtotal, ?string $couponCode, $manualDiscountInput): array
    {
        $subtotal = max(0, round((float) $subtotal, 2));
        $couponCode = $couponCode !== null ? strtoupper(trim($couponCode)) : null;
        if ($couponCode === '') {
            $couponCode = null;
        }

        $manualRequested = round(max(0, (float) ($manualDiscountInput ?? 0)), 2);
        $coupon = $couponCode ? KioskCoupon::where('code', $couponCode)->first() : null;
        $couponDiscount = $coupon ? $coupon->discountAmountFor($subtotal) : 0.0;
        $remainder = max(0, round($subtotal - $couponDiscount, 2));
        $manualDiscount = min($manualRequested, $remainder);
        $discountTotal = round($couponDiscount + $manualDiscount, 2);

        return [
            'coupon_code' => $coupon?->code ?? $couponCode,
            'coupon_discount' => $couponDiscount,
            'manual_discount' => $manualDiscount,
            'discount_total' => $discountTotal,
            'payable' => max(0, round($subtotal - $discountTotal, 2)),
            'coupon' => $coupon,
        ];
    }
}
