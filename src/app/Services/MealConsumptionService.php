<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Order;
use App\Models\ReservationMealConsumption;
use Carbon\Carbon;

class MealConsumptionService
{
    /**
     * Registrar consumo de alimentación
     */
    public function registerConsumption(
        Reservation $reservation,
        Order $order,
        string $mealType,
        bool $isIncluded = false
    ): ReservationMealConsumption {
        // Verificar si es consumo incluido o adicional
        $canConsumeIncluded = $reservation->canConsumeIncludedMeal($mealType);
        
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
            'quantity_consumed' => 1, // Por defecto 1 comida
            'is_included' => $isIncluded,
            'is_additional' => $isAdditional,
            'consumption_date' => Carbon::today(),
        ]);
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
            $included = $reservation->getIncludedMealQuantity($mealType);
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
