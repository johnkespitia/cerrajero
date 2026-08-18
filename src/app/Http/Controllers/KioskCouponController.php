<?php

namespace App\Http\Controllers;

use App\Models\KioskCoupon;
use App\Models\KioskInvoice;
use App\Services\KioskDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KioskCouponController extends Controller
{
    public function index(Request $request)
    {
        $query = KioskCoupon::query();

        if ($request->boolean('active_only')) {
            $query->where('active', true);
        }

        return response()->json($query->orderBy('code')->get());
    }

    /**
     * Valida un cupón contra el subtotal actual (uso en Caja, sin permiso de admin).
     */
    public function validateCode(Request $request, KioskDiscountService $discountService)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'manual_discount' => 'nullable|numeric|min:0',
            'line_prices' => 'nullable|array',
            'line_prices.*' => 'numeric|min:0',
        ]);

        $linePrices = array_map(
            'floatval',
            $request->input('line_prices', [])
        );

        try {
            $resolved = $discountService->resolve(
                (float) $request->input('subtotal'),
                $request->input('code'),
                $request->input('manual_discount', 0),
                $linePrices
            );
        } catch (ValidationException $e) {
            return response()->json([
                'valid' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Cupón inválido.',
                'errors' => $e->errors(),
            ], 422);
        }

        $coupon = $resolved['coupon'];

        return response()->json([
            'valid' => true,
            'coupon_code' => $resolved['coupon_code'],
            'coupon_name' => $coupon?->name,
            'coupon_type' => $coupon?->type,
            'coupon_effect' => $resolved['coupon_effect'],
            'coupon_apply_scope' => $resolved['coupon_apply_scope'],
            'coupon_value' => $coupon?->value,
            'coupon_discount' => $resolved['coupon_discount'],
            'manual_discount' => $resolved['manual_discount'],
            'discount_total' => $resolved['discount_total'],
            'payable' => $resolved['payable'],
            'subtotal' => round((float) $request->input('subtotal'), 2),
        ]);
    }

    public function show(KioskCoupon $kioskCoupon)
    {
        return response()->json($kioskCoupon);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de cupón inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = KioskCoupon::create($this->normalizePayload($request->all()));

        return response()->json($item, 201);
    }

    public function update(Request $request, KioskCoupon $kioskCoupon)
    {
        $validator = Validator::make($request->all(), $this->validationRules($kioskCoupon->id));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de cupón inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kioskCoupon->update($this->normalizePayload($request->all()));

        return response()->json($kioskCoupon->fresh());
    }

    public function destroy(KioskCoupon $kioskCoupon)
    {
        $count = KioskInvoice::where('coupon_code', $kioskCoupon->code)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "No se puede eliminar: {$count} factura(s) usan este código. Puede desactivarlo.",
            ], 422);
        }

        $kioskCoupon->delete();

        return response()->json(null, 204);
    }

    private function validationRules(?int $couponId = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kiosk_coupons', 'code')->ignore($couponId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'effect' => 'nullable|in:discount,increment',
            'apply_scope' => 'nullable|in:cart,item',
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'max_uses' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ];
    }

    private function normalizePayload(array $data): array
    {
        return [
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'type' => $data['type'] ?? 'percentage',
            'effect' => in_array($data['effect'] ?? 'discount', ['discount', 'increment'], true)
                ? $data['effect']
                : 'discount',
            'apply_scope' => in_array($data['apply_scope'] ?? 'cart', ['cart', 'item'], true)
                ? $data['apply_scope']
                : 'cart',
            'value' => $data['value'] ?? 0,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'max_uses' => array_key_exists('max_uses', $data) && $data['max_uses'] !== '' && $data['max_uses'] !== null
                ? (int) $data['max_uses']
                : null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];
    }
}
