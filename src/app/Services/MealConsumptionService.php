<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Order;
use App\Models\ReservationMealConsumption;
use Carbon\Carbon;

class MealConsumptionService
{
    /**
     * Registrar consumo de alimentación.
     * @param int $quantity Cantidad de platos consumidos (por defecto se calcula desde la orden)
     */
    public function registerConsumption(
        Reservation $reservation,
        Order $order,
        string $mealType,
        bool $isIncluded = false,
        int $quantity = null
    ): ReservationMealConsumption {
        if ($quantity === null || $quantity < 1) {
            $quantity = (int) $order->orderItems()->sum('quantity');
            if ($quantity < 1) {
                $quantity = 1;
            }
        }

        // Verificar si es consumo incluido o adicional (por día: fecha de hoy)
        $consumptionDate = Carbon::today();
        $canConsumeIncluded = $reservation->canConsumeIncludedMeal($mealType, $consumptionDate);

        if ($isIncluded && !$canConsumeIncluded) {
            // Si se intenta consumir incluido pero ya no hay disponible, marcar como adicional
            $isIncluded = false;
            $isAdditional = true;
        } else {
            $isAdditional = !$isIncluded;
        }

        return ReservationMealConsumption::create([
            'reservation_id' => $reservation->id,
            'order_id' => $order->id,
            'meal_type' => $mealType,
            'quantity_consumed' => $quantity,
            'is_included' => $isIncluded,
            'is_additional' => $isAdditional,
            'consumption_date' => Carbon::today(),
        ]);
    }

    /**
     * Registrar consumo dividido: parte incluida (plan) y parte adicional (cargo a habitación).
     * Crea uno o dos registros según cantidades.
     *
     * @return ReservationMealConsumption[]
     */
    public function registerConsumptionSplit(
        Reservation $reservation,
        Order $order,
        string $mealType,
        int $totalQuantity
    ): array {
        $date = Carbon::today();
        $remainingIncluded = $reservation->getRemainingIncludedMeals($mealType, $date);
        $includedQty = min($remainingIncluded, $totalQuantity);
        $additionalQty = $totalQuantity - $includedQty;

        $created = [];
        if ($includedQty > 0) {
            $created[] = ReservationMealConsumption::create([
                'reservation_id' => $reservation->id,
                'order_id' => $order->id,
                'meal_type' => $mealType,
                'quantity_consumed' => $includedQty,
                'is_included' => true,
                'is_additional' => false,
                'consumption_date' => $date,
            ]);
        }
        if ($additionalQty > 0) {
            $created[] = ReservationMealConsumption::create([
                'reservation_id' => $reservation->id,
                'order_id' => $order->id,
                'meal_type' => $mealType,
                'quantity_consumed' => $additionalQty,
                'is_included' => false,
                'is_additional' => true,
                'consumption_date' => $date,
            ]);
        }
        return $created;
    }

    /**
     * Obtener resumen de consumo de alimentación
     */
    public function getConsumptionSummary(Reservation $reservation, $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        $summary = [];
        $mealTypes = ['breakfast', 'lunch', 'dinner'];

        foreach ($mealTypes as $mealType) {
            // Por día: incluido ese día (huéspedes con ese tipo de comida en el plan)
            $included = $reservation->getIncludedMealQuantityPerDay($mealType, $date);
            $consumed = $reservation->getMealConsumptionByType($mealType, $date);
            $remaining = $reservation->getRemainingIncludedMeals($mealType, $date);
            $additional = ReservationMealConsumption::getAdditionalConsumption(
                $reservation->id,
                $mealType,
                $date
            );

            $summary[$mealType] = [
                'included' => $included,
                'consumed' => $consumed,
                'remaining' => $remaining,
                'additional' => $additional,
                'can_consume' => $reservation->canConsumeIncludedMeal($mealType, $date),
            ];
        }

        return $summary;
    }

    /**
     * Verificar si puede consumir comida incluida
     */
    public function canConsumeIncludedMeal(Reservation $reservation, string $mealType, $date = null): bool
    {
        return $reservation->canConsumeIncludedMeal($mealType, $date);
    }

    /**
     * Obtener cantidad restante de comidas incluidas
     */
    public function getRemainingIncludedMeals(Reservation $reservation, string $mealType, $date = null): int
    {
        return $reservation->getRemainingIncludedMeals($mealType, $date);
    }
}
