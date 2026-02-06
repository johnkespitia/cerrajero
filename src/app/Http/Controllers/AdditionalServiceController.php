<?php

namespace App\Http\Controllers;

use App\Models\AdditionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdditionalServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = AdditionalService::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }
        if ($request->has('applies_to')) {
            $query->where('applies_to', $request->applies_to);
        }
        if ($request->has('billing_type')) {
            $query->where('billing_type', $request->billing_type);
        }
        if ($request->has('reservation_type')) {
            $query->forReservationType($request->reservation_type);
        }

        $items = $query->orderBy('name')->get();
        return response()->json($items);
    }

    public function show(AdditionalService $additionalService)
    {
        return response()->json($additionalService);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_type' => 'required|in:per_day,one_time',
            'applies_to' => 'required|in:room,day_pass,both',
            'is_per_guest' => 'boolean',
            'status' => 'in:active,inactive',
            'is_food_service' => 'boolean',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->only([
            'name', 'description', 'price', 'billing_type', 'applies_to', 'is_per_guest', 'status'
        ]);
        $data['is_per_guest'] = $request->boolean('is_per_guest', true);
        $data['status'] = $request->input('status', 'active');
        $data['is_food_service'] = $request->boolean('is_food_service', false);
        $data['meal_type'] = in_array($request->input('meal_type'), ['breakfast', 'lunch', 'dinner'], true)
            ? $request->meal_type
            : null;

        $item = AdditionalService::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, AdditionalService $additionalService)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'billing_type' => 'sometimes|in:per_day,one_time',
            'applies_to' => 'sometimes|in:room,day_pass,both',
            'is_per_guest' => 'boolean',
            'status' => 'sometimes|in:active,inactive',
            'is_food_service' => 'boolean',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->only([
            'name', 'description', 'price', 'billing_type', 'applies_to', 'is_per_guest', 'status'
        ]);
        if ($request->has('is_per_guest')) {
            $data['is_per_guest'] = $request->boolean('is_per_guest');
        }
        if ($request->has('is_food_service')) {
            $data['is_food_service'] = $request->boolean('is_food_service');
        }
        // Persistir meal_type siempre que venga en la petición (evita fallos si only() no lo incluye)
        if ($request->has('meal_type')) {
            $data['meal_type'] = in_array($request->meal_type, ['breakfast', 'lunch', 'dinner'], true)
                ? $request->meal_type
                : null;
        }

        $additionalService->update($data);
        return response()->json($additionalService->fresh());
    }

    public function destroy(AdditionalService $additionalService)
    {
        $count = $additionalService->reservationAdditionalServices()->count();
        if ($count > 0) {
            return response()->json([
                'message' => "No se puede eliminar: {$count} reserva(s) tienen este servicio. Puede desactivarlo (status=inactive).",
            ], 422);
        }

        $additionalService->delete();
        return response()->json(null, 204);
    }
}
