<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $query = Promotion::query();

        if ($request->boolean('active_only')) {
            $query->where('active', true);
        }

        $items = $query->orderBy('code')->get();

        return response()->json($items);
    }

    public function show(Promotion $promotion)
    {
        return response()->json($promotion);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $this->normalizePayload($request->all());
        $item = Promotion::create($data);

        return response()->json($item, 201);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $validator = Validator::make($request->all(), $this->validationRules($promotion->id));

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $this->normalizePayload($request->all());
        $promotion->update($data);

        return response()->json($promotion->fresh());
    }

    public function destroy(Promotion $promotion)
    {
        $count = Reservation::where('promotion_code', $promotion->code)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "No se puede eliminar: {$count} reserva(s) usan este código. Puede desactivarlo.",
            ], 422);
        }

        $promotion->delete();

        return response()->json(null, 204);
    }

    private function validationRules(?int $promotionId = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('promotions', 'code')->ignore($promotionId),
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'min_nights' => 'nullable|integer|min:1',
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
            'min_nights' => $data['min_nights'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ];
    }
}
