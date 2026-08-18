<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\AdditionalService;
use App\Models\ReservationAdditionalService;

class AdditionalServicePriceCalculator
{
    /**
     * Días de cobro del servicio según la estadía (noches para habitación, 1 para pasadía).
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
     * Cantidad de ítems por defecto al agregar un servicio (adultos + niños del grupo).
     */
    public function getDefaultItemQuantity(Reservation $reservation): int
    {
        $guests = (int) $reservation->adults + (int) $reservation->children;
        if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            $reservation->loadMissing(['childReservations']);
            $guests += (int) $reservation->childReservations->sum(fn ($r) => $r->adults + $r->children);
        }

        return max(1, $guests);
    }

    /**
     * Calcula el total: precio × cantidad de ítems × días (per_day) o × cantidad (one_time).
     */
    public function calculateTotal(
        AdditionalService $service,
        Reservation $reservation,
        int $itemQuantity = 1
    ): array {
        $itemQuantity = max(1, $itemQuantity);

        if ($service->billing_type === 'one_time') {
            $serviceDays = 1;
            $total = round($service->price * $itemQuantity, 2);
        } else {
            $serviceDays = $this->getServiceDays($reservation);
            $total = round($service->price * $itemQuantity * $serviceDays, 2);
        }

        return [
            'unit_price' => $service->price,
            'quantity' => $itemQuantity,
            'service_days' => $serviceDays,
            'guests_count' => 1,
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
     * Agrega un servicio a la reserva con cantidad de ítems explícita.
     */
    public function addServiceToReservation(
        Reservation $reservation,
        AdditionalService $service,
        int $itemQuantity = 1
    ): ReservationAdditionalService {
        $calc = $this->calculateTotal($service, $reservation, $itemQuantity);

        return $reservation->additionalServices()->create([
            'additional_service_id' => $service->id,
            'unit_price' => $calc['unit_price'],
            'quantity' => $calc['quantity'],
            'service_days' => $calc['service_days'],
            'guests_count' => $calc['guests_count'],
            'total' => $calc['total'],
        ])->load('additionalService');
    }

    /**
     * Actualiza la cantidad de ítems de un servicio ya contratado y recalcula su total.
     */
    public function updateServiceQuantity(
        ReservationAdditionalService $ras,
        Reservation $reservation,
        int $itemQuantity
    ): ReservationAdditionalService {
        $service = $ras->additionalService;
        if (!$service) {
            $service = AdditionalService::findOrFail($ras->additional_service_id);
        }

        $calc = $this->calculateTotal($service, $reservation, $itemQuantity);
        $ras->update([
            'unit_price' => $calc['unit_price'],
            'quantity' => $calc['quantity'],
            'service_days' => $calc['service_days'],
            'guests_count' => $calc['guests_count'],
            'total' => $calc['total'],
        ]);

        return $ras->fresh()->load('additionalService');
    }

    /**
     * Aplica los servicios de un paquete a la reserva (evitando duplicados del mismo servicio).
     */
    public function applyPackageToReservation(
        Reservation $reservation,
        \App\Models\ServicePackage $package,
        ?array $quantitiesByServiceId = null
    ): array {
        $added = [];
        $existingIds = $reservation->additionalServices()->pluck('additional_service_id')->toArray();
        $quantitiesByServiceId = $quantitiesByServiceId ?? [];

        foreach ($package->additionalServices as $service) {
            if (in_array($service->id, $existingIds, true)) {
                continue;
            }
            if (!$this->serviceAppliesToReservationType($service, $reservation->reservation_type)) {
                continue;
            }
            $qty = max(1, (int) ($quantitiesByServiceId[$service->id] ?? $this->getDefaultItemQuantity($reservation)));
            $added[] = $this->addServiceToReservation($reservation, $service, $qty);
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
     * Recalcula los totales al cambiar fechas (actualiza días, conserva cantidad de ítems).
     */
    public function recalculateReservationAdditionalServices(Reservation $reservation): void
    {
        foreach ($reservation->additionalServices as $ras) {
            $service = $ras->additionalService;
            if (!$service) {
                continue;
            }
            $itemQuantity = max(1, (int) $ras->quantity);
            $calc = $this->calculateTotal($service, $reservation, $itemQuantity);
            $ras->update([
                'unit_price' => $calc['unit_price'],
                'service_days' => $calc['service_days'],
                'guests_count' => 1,
                'total' => $calc['total'],
            ]);
        }

        $reservation->recomputeFinalPrice();
    }
}
