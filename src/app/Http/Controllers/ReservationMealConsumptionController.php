<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationMealConsumption;
use App\Services\MealConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservationMealConsumptionController extends Controller
{
    protected $mealConsumptionService;

    public function __construct(MealConsumptionService $mealConsumptionService)
    {
        $this->mealConsumptionService = $mealConsumptionService;
    }

    /**
     * Listar consumo de alimentación de una reserva
     */
    public function index($reservationId)
    {
        $reservation = Reservation::findOrFail($reservationId);

        $consumptions = ReservationMealConsumption::where('reservation_id', $reservationId)
            ->with(['order', 'reservation'])
            ->orderBy('consumption_date', 'desc')
            ->orderBy('meal_type')
            ->get()
            ->groupBy(function($consumption) {
                return $consumption->consumption_date->format('Y-m-d') . '_' . $consumption->meal_type;
            })
            ->map(function($group) {
                return [
                    'date' => $group->first()->consumption_date,
                    'meal_type' => $group->first()->meal_type,
                    'total_consumed' => $group->sum('quantity_consumed'),
                    'included' => $group->where('is_included', true)->sum('quantity_consumed'),
                    'additional' => $group->where('is_additional', true)->sum('quantity_consumed'),
                    'consumptions' => $group,
                ];
            })
            ->values();

        return response()->json([
            'reservation_id' => $reservationId,
            'summary' => $this->mealConsumptionService->getConsumptionSummary($reservation),
            'consumptions' => $consumptions,
        ]);
    }

    /**
     * Registrar consumo de alimentación
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'order_id' => 'nullable|exists:orders,id',
            'meal_type' => 'required|in:breakfast,lunch,dinner',
            'quantity_consumed' => 'required|integer|min:1',
            'is_included' => 'boolean',
            'consumption_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $reservation = Reservation::findOrFail($request->reservation_id);
        $order = $request->order_id ? \App\Models\Order::find($request->order_id) : null;

        // Validar si es incluido o adicional
        $isIncluded = $request->is_included ?? false;
        
        if ($isIncluded && !$reservation->canConsumeIncludedMeal($request->meal_type, $request->consumption_date)) {
            // Si se intenta consumir incluido pero no hay disponible, marcar como adicional
            $isIncluded = false;
            $isAdditional = true;
        } else {
            $isAdditional = !$isIncluded;
        }

        $consumption = ReservationMealConsumption::create([
            'reservation_id' => $request->reservation_id,
            'order_id' => $request->order_id,
            'meal_type' => $request->meal_type,
            'quantity_consumed' => $request->quantity_consumed,
            'is_included' => $isIncluded,
            'is_additional' => $isAdditional,
            'consumption_date' => $request->consumption_date ?? now()->toDateString(),
        ]);

        return response()->json($consumption->load(['reservation', 'order']), 201);
    }

    /**
     * Mostrar consumo específico
     */
    public function show(ReservationMealConsumption $reservationMealConsumption)
    {
        $reservationMealConsumption->load(['reservation', 'order']);
        return response()->json($reservationMealConsumption);
    }

    /**
     * Eliminar consumo (solo si no está asociado a una orden)
     */
    public function destroy(ReservationMealConsumption $reservationMealConsumption)
    {
        if ($reservationMealConsumption->order_id) {
            return response()->json([
                'message' => 'No se puede eliminar un consumo asociado a una orden'
            ], 422);
        }

        $reservationMealConsumption->delete();

        return response()->json(null, 204);
    }
}
