<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ReservationValidationService
{
    /**
     * Validar fechas avanzadas
     */
    public function validateDates($checkInDate, $checkOutDate = null, $reservationType = 'room')
    {
        $errors = [];

        $checkIn = Carbon::parse($checkInDate);
        $today = Carbon::today();

        // No permitir fechas pasadas
        if ($checkIn->lt($today)) {
            $errors['check_in_date'] = 'No se pueden hacer reservas para fechas pasadas.';
        }

        // Validar límite de anticipación
        $maxAdvanceDays = ReservationSetting::getInt('max_advance_days', 365);
        $maxDate = $today->copy()->addDays($maxAdvanceDays);
        if ($checkIn->gt($maxDate)) {
            $errors['check_in_date'] = "No se pueden hacer reservas con más de {$maxAdvanceDays} días de anticipación.";
        }

        if ($checkOutDate) {
            $checkOut = Carbon::parse($checkOutDate);

            if ($reservationType === 'day_pass') {
                // Para pasadía, las fechas deben ser iguales
                if (!$checkIn->isSameDay($checkOut)) {
                    $errors['check_out_date'] = 'Para pasadía, la fecha de salida debe ser la misma que la de entrada.';
                }
            } else {
                // Para habitaciones, validar mínimo de estadía
                $minNights = ReservationSetting::getInt('min_stay_nights', 1);
                $nights = $checkIn->diffInDays($checkOut);
                
                if ($nights < $minNights) {
                    $errors['check_out_date'] = "La estadía mínima es de {$minNights} noche(s).";
                }

                // Validar máximo de estadía
                $maxNights = ReservationSetting::getInt('max_stay_nights', 30);
                if ($nights > $maxNights) {
                    $errors['check_out_date'] = "La estadía máxima es de {$maxNights} noche(s).";
                }

                // Validar que check-out sea después de check-in
                if ($checkOut->lte($checkIn)) {
                    $errors['check_out_date'] = 'La fecha de salida debe ser posterior a la fecha de entrada.';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validar límites de reserva por cliente
     */
    public function validateCustomerLimits($customerId, $checkInDate, $checkOutDate = null)
    {
        $maxReservations = ReservationSetting::getInt('max_reservations_per_customer', 5);
        
        $query = Reservation::where('customer_id', $customerId)
            ->where('status', '!=', 'cancelled');

        if ($checkOutDate) {
            $query->where(function ($q) use ($checkInDate, $checkOutDate) {
                $q->whereBetween('check_in_date', [$checkInDate, $checkOutDate])
                    ->orWhereBetween('check_out_date', [$checkInDate, $checkOutDate])
                    ->orWhere(function ($q2) use ($checkInDate, $checkOutDate) {
                        $q2->where('check_in_date', '<=', $checkInDate)
                            ->where('check_out_date', '>=', $checkOutDate);
                    });
            });
        } else {
            $query->where('check_in_date', '>=', $checkInDate);
        }

        $activeReservations = $query->count();

        if ($activeReservations >= $maxReservations) {
            return [
                'valid' => false,
                'message' => "El cliente ya tiene {$activeReservations} reserva(s) activa(s). El límite es de {$maxReservations}.",
            ];
        }

        return [
            'valid' => true,
            'message' => null,
        ];
    }

    /**
     * Validar formato de documento según tipo
     */
    public function validateDocument($documentType, $documentNumber)
    {
        if (!$documentNumber || !$documentType) {
            return ['valid' => true, 'message' => null];
        }

        $patterns = [
            'CC' => '/^\d{6,10}$/', // Cédula de ciudadanía colombiana
            'CE' => '/^[A-Z0-9]{5,20}$/', // Cédula de extranjería
            'PA' => '/^[A-Z0-9]{5,20}$/', // Pasaporte
            'NIT' => '/^\d{9,11}(-?\d{1})?$/', // NIT colombiano
        ];

        $pattern = $patterns[$documentType] ?? null;

        if (!$pattern) {
            return ['valid' => true, 'message' => null]; // Tipo desconocido, no validar
        }

        if (!preg_match($pattern, $documentNumber)) {
            return [
                'valid' => false,
                'message' => "El formato del documento de tipo {$documentType} no es válido.",
            ];
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * Validar horarios de check-in/check-out
     */
    public function validateCheckInOutTimes($checkInTime = null, $checkOutTime = null)
    {
        $errors = [];

        $defaultCheckIn = ReservationSetting::get('check_in_time', '15:00');
        $defaultCheckOut = ReservationSetting::get('check_out_time', '12:00');

        // Validaciones básicas (se pueden expandir)
        if ($checkInTime) {
            $time = Carbon::parse($checkInTime);
            $minTime = Carbon::parse('06:00');
            $maxTime = Carbon::parse('23:59');
            
            if ($time->lt($minTime) || $time->gt($maxTime)) {
                $errors['check_in_time'] = 'El horario de check-in debe estar entre las 06:00 y 23:59.';
            }
        }

        if ($checkOutTime) {
            $time = Carbon::parse($checkOutTime);
            $minTime = Carbon::parse('06:00');
            $maxTime = Carbon::parse('23:59');
            
            if ($time->lt($minTime) || $time->gt($maxTime)) {
                $errors['check_out_time'] = 'El horario de check-out debe estar entre las 06:00 y 23:59.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Verificar si una reserva está activa (considerando pasadías)
     */
    public function isReservationActive(Reservation $reservation): bool
    {
        if ($reservation->status === 'checked_in') {
            return true;
        }

        // Para pasadías, considerar también checked_out del mismo día
        if ($reservation->reservation_type === 'day_pass') {
            if ($reservation->status === 'checked_out') {
                $checkInDate = Carbon::parse($reservation->check_in_date);
                $today = Carbon::today();
                
                // Si el check-out fue el mismo día del check-in, aún está "activa" para efectos de consumo
                return $checkInDate->isSameDay($today);
            }
        }

        return false;
    }

    /**
     * Obtener reserva activa de un cliente
     */
    public function getActiveReservationForCustomer(int $customerId, $date = null): ?Reservation
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        // Para reservas normales: estado checked_in
        $normalReservation = Reservation::where('customer_id', $customerId)
            ->where('status', 'checked_in')
            ->where('check_in_date', '<=', $date)
            ->where(function($query) use ($date) {
                $query->whereNull('check_out_date')
                    ->orWhere('check_out_date', '>=', $date);
            })
            ->first();

        if ($normalReservation) {
            return $normalReservation;
        }

        // Para pasadías: checked_in o checked_out del mismo día
        $dayPassReservation = Reservation::where('customer_id', $customerId)
            ->where('reservation_type', 'day_pass')
            ->whereDate('check_in_date', $date)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->first();

        return $dayPassReservation;
    }
}

