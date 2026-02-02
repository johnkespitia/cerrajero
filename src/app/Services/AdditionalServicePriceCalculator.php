<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\AdditionalService;
use App\Models\ReservationAdditionalService;

class AdditionalServicePriceCalculator
{
    /**
     * Calcula los "días de servicio" con lógica hotelera:
     * - Check-in: solo cena (media jornada)
     * - Check-out: desayuno + almuerzo (media jornada)
     * - Check-in + Check-out = 1 día completo
     * - Días intermedios = 1 día cada uno
     * Para pasadía: 1 día.
     */
    public function getServiceDays(Reservation $reservation): float
    {
        if ($reservation->reservation_type === 'day_pass') {
            return 1;
        }

        $nights = $reservation->nights;
        if ($nights < 1) {
            return 1;
        }

        return (float) $nights;
    }

    /**
     * Calcula el total de un servicio adicional para una reserva.
     */
    public function calculateTotal(
        AdditionalService $service,
        Reservation $reservation,
        ?int $guestsOverride = null
    ): array {
        $guests = $guestsOverride ?? ($reservation->adults + $reservation->children);
        $guests = max(1, $guests);

        if ($service->billing_type === 'one_time') {
            $quantity = 1;
        } else {
            $quantity = $this->getServiceDays($reservation);
        }

        $multiplier = $service->is_per_guest ? $guests : 1;
        $total = round($service->price * $quantity * $multiplier, 2);

        return [
            'unit_price' => $service->price,
            'quantity' => $quantity,
            'guests_count' => $guests,
            'total' => $total,
        ];
    }

    /**
     * Suma el total de todos los servicios adicionales de una reserva.
     */
    public function getReservationAdditionalServicesTotal(Reservation $reservation): float
    {
        return (float) $reservation->additionalServices()->sum('total');
    }

    /**
     * Agrega un servicio a la reserva y calcula su total.
     */
    public function addServiceToReservation(
        Reservation $reservation,
        AdditionalService $service,
        ?int $guestsOverride = null
    ): ReservationAdditionalService {
        $calc = $this->calculateTotal($service, $reservation, $guestsOverride);

        return $reservation->additionalServices()->create([
            'additional_service_id' => $service->id,
            'unit_price' => $calc['unit_price'],
            'quantity' => $calc['quantity'],
            'guests_count' => $calc['guests_count'],
            'total' => $calc['total'],
        ])->load('additionalService');
    }

    /**
     * Aplica los servicios de un paquete a la reserva (evitando duplicados del mismo servicio).
     */
    public function applyPackageToReservation(
        Reservation $reservation,
        \App\Models\ServicePackage $package,
        ?int $guestsOverride = null
    ): array {
        $added = [];
        $existingIds = $reservation->additionalServices()->pluck('additional_service_id')->toArray();

        foreach ($package->additionalServices as $service) {
            if (in_array($service->id, $existingIds, true)) {
                continue;
            }
            if (!$this->serviceAppliesToReservationType($service, $reservation->reservation_type)) {
                continue;
            }
            $added[] = $this->addServiceToReservation($reservation, $service, $guestsOverride);
        }

        return $added;
    }

    public function serviceAppliesToReservationType(AdditionalService $service, string $reservationType): bool
    {
        if ($service->applies_to === 'both') {
            return true;
        }
        return $service->applies_to === $reservationType;
    }

    /**
     * Recalcula los totales de los servicios adicionales de una reserva (p. ej. al cambiar fechas o huéspedes).
     * Para reservas con varias habitaciones (grupo), usa el total de huéspedes de todas las habitaciones.
     */
    public function recalculateReservationAdditionalServices(Reservation $reservation): void
    {
        $guests = $reservation->adults + $reservation->children;
        if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            $reservation->loadMissing(['childReservations']);
            $guests = $guests + $reservation->childReservations->sum(fn ($r) => $r->adults + $r->children);
        }
        $guests = max(1, (int) $guests);

        foreach ($reservation->additionalServices as $ras) {
            $service = $ras->additionalService;
            if (!$service) {
                continue;
            }
            $calc = $this->calculateTotal($service, $reservation, $guests);
            $ras->update([
                'unit_price' => $calc['unit_price'],
                'quantity' => $calc['quantity'],
                'guests_count' => $calc['guests_count'],
                'total' => $calc['total'],
            ]);
        }

        $reservation->recomputeFinalPrice();
    }
}
