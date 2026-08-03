<?php

namespace App\Http\Controllers;

use App\Models\KioskCoupon;
use App\Models\KioskInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
