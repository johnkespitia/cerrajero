<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomSeason;
use App\Models\Promotion;
use Carbon\Carbon;

class ReservationPriceCalculator
{
    public function calculatePrice(Reservation $reservation, $forceRecalculate = false)
    {
        // Si hay override manual y no se fuerza recálculo, usar precio manual
        if ($reservation->manual_price_override && !$forceRecalculate) {
            return $reservation->total_price;
        }

        if ($reservation->reservation_type === 'day_pass') {
            return $this->calculateDayPassPrice($reservation);
        }

        return $this->calculateRoomPrice($reservation);
    }

    protected function calculateDayPassPrice(Reservation $reservation)
    {
        $dayPassCapacity = \App\Models\DayPassCapacity::getOrCreateForDate(
            $reservation->check_in_date->format('Y-m-d'),
            0,
            0,
            0
        );

        $adults = $reservation->adults ?? 1;
        $children = $reservation->children ?? 0;
        $basePrice = $dayPassCapacity->calculatePrice($adults, $children);

        $breakdown = [
            'base_price' => $basePrice,
            'adults' => $adults,
            'children' => $children,
            'adult_price' => $dayPassCapacity->adult_price,
            'child_price' => $dayPassCapacity->child_price,
        ];

        // Aplicar descuentos
        $discount = $this->calculateDiscount($reservation, $basePrice);
        $finalPrice = max(0, $basePrice - $discount);

        $breakdown['discount'] = $discount;
        $breakdown['final_price'] = $finalPrice;

        return [
            'calculated_price' => $finalPrice,
            'price_breakdown' => $breakdown,
        ];
    }

    protected function calculateRoomPrice(Reservation $reservation)
    {
        $room = $reservation->room;
        $roomType = $reservation->roomType ?? ($room ? $room->roomType : null);

        if (!$room && !$roomType) {
            return [
                'calculated_price' => 0,
                'price_breakdown' => ['error' => 'No room or room type specified'],
            ];
        }

        $nights = $reservation->nights;
        $adults = $reservation->adults ?? 1;
        $children = $reservation->children ?? 0;
        $infants = $reservation->infants ?? 0;
        $extraBeds = $reservation->extra_beds ?? 0;

        // Precio base por persona por noche
        $basePricePerPersonPerNight = $room ? $room->room_price : ($roomType ? $roomType->base_price : 0);
        
        // Número de personas que se cobran (adultos + niños, los bebés no se cobran)
        $chargeableGuests = $adults + $children;
        
        // Aplicar temporada si existe
        $seasonMultiplier = 1.0;
        $seasonFixedPrice = null;
        if ($roomType) {
            $season = RoomSeason::getSeasonForDate(
                $roomType->id,
                $reservation->check_in_date->format('Y-m-d')
            );
            
            if ($season) {
                $seasonMultiplier = $season->price_multiplier;
                $seasonFixedPrice = $season->fixed_price;
            }
        }

        // Calcular precio base de noches
        // Si hay precio fijo de temporada, se aplica por persona por noche
        if ($seasonFixedPrice !== null) {
            $totalNightsPrice = $seasonFixedPrice * $chargeableGuests * $nights;
        } else {
            // Precio base por persona por noche × número de personas × noches × multiplicador de temporada
            $totalNightsPrice = $basePricePerPersonPerNight * $chargeableGuests * $nights * $seasonMultiplier;
        }

        // Calcular personas adicionales
        $capacity = $room ? $room->capacity : ($roomType ? $roomType->default_capacity : 0);
        $extraPersons = max(0, ($adults + $children) - $capacity);
        $extraPersonPrice = $room ? $room->extra_person_price : 0;
        $extraPersonCost = $extraPersons * $extraPersonPrice * $nights;

        // Calcular camas adicionales
        $extraBedPrice = $room ? $room->extra_bed_price : 0;
        $extraBedCost = $extraBeds * $extraBedPrice * $nights;

        // Calcular tarifas de check-in/check-out temprano/tardío
        $earlyCheckInFee = $reservation->early_check_in ? ($reservation->early_check_in_fee ?? 0) : 0;
        $lateCheckOutFee = $reservation->late_check_out ? ($reservation->late_check_out_fee ?? 0) : 0;

        $subtotal = $totalNightsPrice + $extraPersonCost + $extraBedCost + $earlyCheckInFee + $lateCheckOutFee;

        // Aplicar descuentos
        $discount = $this->calculateDiscount($reservation, $subtotal, $nights);
        $finalPrice = max(0, $subtotal - $discount);

        $breakdown = [
            'base_price_per_person_per_night' => $basePricePerPersonPerNight,
            'chargeable_guests' => $chargeableGuests,
            'adults' => $adults,
            'children' => $children,
            'nights' => $nights,
            'season_multiplier' => $seasonMultiplier,
            'season_fixed_price' => $seasonFixedPrice,
            'total_nights_price' => $totalNightsPrice,
            'capacity' => $capacity,
            'extra_persons' => $extraPersons,
            'extra_person_price' => $extraPersonPrice,
            'extra_person_cost' => $extraPersonCost,
            'extra_beds' => $extraBeds,
            'extra_bed_price' => $extraBedPrice,
            'extra_bed_cost' => $extraBedCost,
            'early_check_in_fee' => $earlyCheckInFee,
            'late_check_out_fee' => $lateCheckOutFee,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'final_price' => $finalPrice,
        ];

        return [
            'calculated_price' => $finalPrice,
            'price_breakdown' => $breakdown,
        ];
    }

    protected function calculateDiscount(Reservation $reservation, $basePrice, $nights = 1)
    {
        $totalDiscount = 0;

        // Descuento por código promocional
        if ($reservation->promotion_code) {
            $promotion = Promotion::where('code', $reservation->promotion_code)->first();
            if ($promotion && $promotion->isValid($reservation->check_in_date->format('Y-m-d'), $nights)) {
                $discount = $promotion->calculateDiscount($basePrice, $nights);
                $totalDiscount += $discount;
            }
        }

        // Descuento manual
        if ($reservation->discount_amount) {
            $totalDiscount += $reservation->discount_amount;
        }

        return min($totalDiscount, $basePrice);
    }
}

