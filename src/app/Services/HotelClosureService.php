<?php

namespace App\Services;

use App\Models\HotelClosure;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HotelClosureService
{
    public function hasClosureConflict(string $checkIn, string $checkOut): bool
    {
        return $this->getConflictingClosure($checkIn, $checkOut) !== null;
    }

    public function getClosureDetails(string $start, string $end): ?array
    {
        $startDate = Carbon::parse($start)->toDateString();
        $endDate = Carbon::parse($end)->toDateString();

        $closure = HotelClosure::active()->overlapping($startDate, $endDate)->first();
        if (!$closure) {
            return null;
        }
        return [
            'start' => $closure->start_date->format('Y-m-d'),
            'end' => $closure->end_date->format('Y-m-d'),
            'reason' => $closure->reason,
        ];
    }

    public function hasActiveReservationsInRange(string $start, string $end): bool
    {
        return $this->getConflictingReservations($start, $end)->isNotEmpty();
    }

    public function getConflictingReservations(string $start, string $end): Collection
    {
        $startDate = Carbon::parse($start)->toDateString();
        $endDate = Carbon::parse($end)->toDateString();

        return Reservation::whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->where('check_in_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('check_out_date')
                  ->orWhere('check_out_date', '>=', $startDate);
            })
            ->with(['customer', 'room'])
            ->get();
    }

    public function validateClosureCreation(string $start, string $end): array
    {
        try {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->startOfDay();
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Formato de fecha inválido.', 'conflicts' => collect()];
        }

        if ($startDate->gt($endDate)) {
            return ['valid' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha fin.', 'conflicts' => collect()];
        }

        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        $overlappingClosure = HotelClosure::active()->overlapping($startStr, $endStr)->first();
        if ($overlappingClosure) {
            return [
                'valid' => false,
                'message' => 'Ya existe un cierre del ' . $overlappingClosure->start_date->format('Y-m-d') . ' al ' . $overlappingClosure->end_date->format('Y-m-d') . '.',
                'conflicts' => collect([$overlappingClosure]),
            ];
        }

        $conflictingReservations = $this->getConflictingReservations($startStr, $endStr);
        if ($conflictingReservations->isNotEmpty()) {
            return [
                'valid' => false,
                'message' => 'Existen ' . $conflictingReservations->count() . ' reserva(s) activa(s) que solapan con el periodo de cierre.',
                'conflicts' => $conflictingReservations,
            ];
        }

        return ['valid' => true, 'message' => null, 'conflicts' => collect()];
    }
}
