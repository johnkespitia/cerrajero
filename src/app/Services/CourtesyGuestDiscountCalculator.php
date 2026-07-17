<?php

namespace App\Services;

use App\Models\Reservation;

class CourtesyGuestDiscountCalculator
{
    /**
     * Huéspedes cobrables (adultos + niños) para la reserva o el grupo.
     */
    public function getChargeableGuests(Reservation $reservation): int
    {
        if ($reservation->parent_reservation_id) {
            return 0;
        }

        if ($reservation->is_group_reservation) {
            $reservation->loadMissing('childReservations');
            $total = (int) $reservation->adults + (int) $reservation->children;
            foreach ($reservation->childReservations as $child) {
                $total += (int) $child->adults + (int) $child->children;
            }

            return max(0, $total);
        }

        return max(0, (int) $reservation->adults + (int) $reservation->children);
    }

    /**
     * Hospedaje/pasadía neto (después de cupón y descuento manual) para toda la reserva o grupo.
     */
    public function getLodgingNetTotal(Reservation $reservation): float
    {
        if ($reservation->parent_reservation_id) {
            return 0.0;
        }

        $total = (float) ($reservation->calculated_price ?? $reservation->total_price ?? 0);

        if ($reservation->is_group_reservation) {
            $reservation->loadMissing('childReservations');
            foreach ($reservation->childReservations as $child) {
                $total += (float) ($child->calculated_price ?? $child->total_price ?? 0);
            }
        }

        return max(0.0, $total);
    }

    public function getCourtesyGuestCount(Reservation $reservation): int
    {
        if ($reservation->parent_reservation_id) {
            return 0;
        }

        $chargeable = $this->getChargeableGuests($reservation);

        return min(max(0, (int) ($reservation->courtesy_guests ?? 0)), $chargeable);
    }

    /**
     * Descuento por cortesías: por cada huésped de cortesía resta su parte de hospedaje
     * (precio neto con cupón/descuentos) más su parte de cada servicio adicional.
     */
    public function calculate(Reservation $reservation): array
    {
        $courtesyCount = $this->getCourtesyGuestCount($reservation);
        $chargeable = $this->getChargeableGuests($reservation);

        if ($courtesyCount <= 0 || $chargeable <= 0) {
            return [
                'courtesy_guests' => 0,
                'chargeable_guests' => $chargeable,
                'lodging_per_guest' => 0.0,
                'services_per_guest' => 0.0,
                'per_guest_total' => 0.0,
                'total' => 0.0,
            ];
        }

        $lodgingNet = $this->getLodgingNetTotal($reservation);
        $lodgingPerGuest = round($lodgingNet / $chargeable, 2);

        $servicesPerGuest = 0.0;
        $reservation->loadMissing('additionalServices');
        foreach ($reservation->additionalServices as $ras) {
            $qty = max(1, (int) $ras->quantity);
            $servicesPerGuest += (float) $ras->total / $qty;
        }
        $servicesPerGuest = round($servicesPerGuest, 2);

        $perGuestTotal = round($lodgingPerGuest + $servicesPerGuest, 2);
        $total = round($perGuestTotal * $courtesyCount, 2);

        return [
            'courtesy_guests' => $courtesyCount,
            'chargeable_guests' => $chargeable,
            'lodging_per_guest' => $lodgingPerGuest,
            'services_per_guest' => $servicesPerGuest,
            'per_guest_total' => $perGuestTotal,
            'total' => $total,
        ];
    }
}
