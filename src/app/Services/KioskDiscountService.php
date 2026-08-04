<?php

namespace App\Services;

use App\Models\KioskCoupon;
use App\Models\KioskInvoice;
use Illuminate\Validation\ValidationException;

class KioskDiscountService
{
    /**
     * @param  array<int, float>  $linePrices
     * @return array{
     *   coupon_code: ?string,
     *   coupon_effect: ?string,
     *   coupon_apply_scope: ?string,
     *   coupon_discount: float,
     *   manual_discount: float,
     *   discount_total: float,
     *   payable: float,
     *   coupon: ?KioskCoupon
     * }
     */
    public function resolve(
        float $subtotal,
        ?string $couponCode,
        $manualDiscountInput,
        array $linePrices = []
    ): array {
        $subtotal = max(0, round((float) $subtotal, 2));
        $couponCode = $couponCode !== null ? strtoupper(trim($couponCode)) : null;
        if ($couponCode === '') {
            $couponCode = null;
        }

        $manualRequested = round(max(0, (float) ($manualDiscountInput ?? 0)), 2);

        $coupon = null;
        $couponAmount = 0.0;
        $effect = null;
        $scope = null;

        if ($couponCode !== null) {
            $coupon = KioskCoupon::where('code', $couponCode)->first();
            if (!$coupon || !$coupon->isValid()) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['El cupón no es válido, está vencido o agotó sus usos.'],
                ]);
            }
            $couponAmount = $coupon->calculateAdjustment($subtotal, $linePrices);
            $effect = $coupon->effect ?? KioskCoupon::EFFECT_DISCOUNT;
            $scope = $coupon->apply_scope ?? KioskCoupon::SCOPE_CART;
        }

        if ($effect === KioskCoupon::EFFECT_INCREMENT) {
            $afterCoupon = round($subtotal + $couponAmount, 2);
            $manualDiscount = min($manualRequested, max(0, $afterCoupon));
            $discountTotal = round($manualDiscount - $couponAmount, 2); // neto: negativo si solo incremento
            $payable = max(0, round($afterCoupon - $manualDiscount, 2));
        } else {
            $remainder = max(0, round($subtotal - $couponAmount, 2));
            $manualDiscount = min($manualRequested, $remainder);
            $discountTotal = round($couponAmount + $manualDiscount, 2);
            $payable = max(0, round($subtotal - $discountTotal, 2));
        }

        return [
            'coupon_code' => $coupon?->code,
            'coupon_effect' => $effect,
            'coupon_apply_scope' => $scope,
            'coupon_discount' => $couponAmount,
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
        $invoice->coupon_effect = $resolved['coupon_effect'] ?? null;
        $invoice->coupon_apply_scope = $resolved['coupon_apply_scope'] ?? null;
        $invoice->coupon_discount = $resolved['coupon_discount'];
        $invoice->manual_discount = $resolved['manual_discount'];
        $invoice->discount_total = $resolved['discount_total'];
        $invoice->manual_discount_by = ((float) $resolved['manual_discount'] > 0) ? $userId : null;
        $invoice->save();

        if ($incrementUsage && ($resolved['coupon'] ?? null) instanceof KioskCoupon) {
            $resolved['coupon']->incrementUsage();
        }
    }

    /**
     * @param  array<int, float>  $linePrices
     */
    public function recalculateExisting(
        float $subtotal,
        ?string $couponCode,
        $manualDiscountInput,
        array $linePrices = []
    ): array {
        $subtotal = max(0, round((float) $subtotal, 2));
        $couponCode = $couponCode !== null ? strtoupper(trim($couponCode)) : null;
        if ($couponCode === '') {
            $couponCode = null;
        }

        $manualRequested = round(max(0, (float) ($manualDiscountInput ?? 0)), 2);
        $coupon = $couponCode ? KioskCoupon::where('code', $couponCode)->first() : null;
        $couponAmount = $coupon ? $coupon->calculateAdjustment($subtotal, $linePrices) : 0.0;
        // Bypass isValid for recalculation of already-applied coupons
        if ($coupon && !$coupon->isValid()) {
            $couponAmount = $coupon->isItemScope()
                ? round(array_sum(array_map(fn ($p) => $coupon->amountForBase((float) $p), $linePrices)), 2)
                : $coupon->amountForBase($subtotal);
        }

        $effect = $coupon?->effect ?? KioskCoupon::EFFECT_DISCOUNT;
        $scope = $coupon?->apply_scope ?? KioskCoupon::SCOPE_CART;

        if ($effect === KioskCoupon::EFFECT_INCREMENT) {
            $afterCoupon = round($subtotal + $couponAmount, 2);
            $manualDiscount = min($manualRequested, max(0, $afterCoupon));
            $discountTotal = round($manualDiscount - $couponAmount, 2);
            $payable = max(0, round($afterCoupon - $manualDiscount, 2));
        } else {
            $remainder = max(0, round($subtotal - $couponAmount, 2));
            $manualDiscount = min($manualRequested, $remainder);
            $discountTotal = round($couponAmount + $manualDiscount, 2);
            $payable = max(0, round($subtotal - $discountTotal, 2));
        }

        return [
            'coupon_code' => $coupon?->code ?? $couponCode,
            'coupon_effect' => $coupon ? $effect : null,
            'coupon_apply_scope' => $coupon ? $scope : null,
            'coupon_discount' => $couponAmount,
            'manual_discount' => $manualDiscount,
            'discount_total' => $discountTotal,
            'payable' => $payable,
            'coupon' => $coupon,
        ];
    }
}
