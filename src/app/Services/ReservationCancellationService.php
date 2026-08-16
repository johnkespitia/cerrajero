<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\CancellationPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReservationCancellationService
{
    /**
     * Calcular reembolso y penalización para una reserva cancelada
     */
    public function calculateRefund(Reservation $reservation)
    {
        // Obtener la política de cancelación
        $policy = $reservation->cancellationPolicy;
        
        if (!$policy) {
            // Si no hay política, no hay reembolso
            Log::warning("Reserva #{$reservation->reservation_number} no tiene política de cancelación asignada");
            return [
                'refund_amount' => 0,
                'penalty_amount' => 0,
                'can_refund' => false,
                'policy' => null,
            ];
        }

        // Si la reserva es gratis, no hay reembolso
        if ($reservation->payment_status === 'free') {
            return [
                'refund_amount' => 0,
                'penalty_amount' => 0,
                'can_refund' => false,
                'policy' => $policy,
                'reason' => 'Reserva gratuita',
            ];
        }

        // Calcular total pagado (solo pagos reales; excluye cargos a habitación)
        $totalPaid = $reservation->total_paid;
        
        if ($totalPaid <= 0) {
            return [
                'refund_amount' => 0,
                'penalty_amount' => 0,
                'can_refund' => false,
                'policy' => $policy,
                'reason' => 'No se ha realizado ningún pago',
            ];
        }

        // Calcular penalización
        $penaltyAmount = $this->calculatePenalty($reservation, $policy);
        
        // Calcular reembolso
        $refundAmount = max(0, $totalPaid - $penaltyAmount);

        return [
            'refund_amount' => $refundAmount,
            'penalty_amount' => $penaltyAmount,
            'total_paid' => $totalPaid,
            'can_refund' => $refundAmount > 0,
            'policy' => $policy,
            'days_until_checkin' => $this->getDaysUntilCheckIn($reservation),
            'before_deadline' => $this->isBeforeDeadline($reservation, $policy),
        ];
    }

    /**
     * Calcular penalización según la política
     */
    protected function calculatePenalty(Reservation $reservation, CancellationPolicy $policy)
    {
        // Si es política gratuita, no hay penalización
        if ($policy->policy_type === 'free') {
            return 0;
        }

        // Si es no reembolsable, penalización = total pagado
        if ($policy->policy_type === 'non_refundable') {
            return $reservation->total_paid;
        }

        // Si es parcial, calcular según días y penalización
        $totalPaid = $reservation->total_paid;
        $finalPrice = $reservation->final_price ?? $reservation->total_price;
        
        // Usar el menor entre total pagado y precio final
        $baseAmount = min($totalPaid, $finalPrice);

        // Verificar si está antes del deadline
        $isBeforeDeadline = $this->isBeforeDeadline($reservation, $policy);

        if ($isBeforeDeadline) {
            // Si cancela antes del deadline, no hay penalización (o penalización mínima si está configurada)
            return $policy->penalty_fee; // Solo tarifa fija si aplica
        }

        // Si cancela después del deadline, aplicar penalización
        $penaltyByPercentage = ($baseAmount * $policy->penalty_percentage) / 100;
        $penaltyByFee = $policy->penalty_fee;

        // Usar el mayor entre porcentaje y tarifa fija
        return max($penaltyByPercentage, $penaltyByFee);
    }

    /**
     * Verificar si la cancelación es antes del deadline
     */
    protected function isBeforeDeadline(Reservation $reservation, CancellationPolicy $policy)
    {
        if (!$policy->cancellation_days_before) {
            return true; // Si no hay deadline, se considera antes del deadline
        }

        $deadline = $reservation->cancellation_deadline;
        
        if (!$deadline) {
            // Calcular deadline si no está guardado
            $deadline = $policy->calculateDeadline($reservation->check_in_date);
        }

        return now()->lte($deadline);
    }

    /**
     * Obtener días restantes hasta el check-in
     */
    protected function getDaysUntilCheckIn(Reservation $reservation)
    {
        $checkIn = Carbon::parse($reservation->check_in_date);
        $now = Carbon::now();
        
        return max(0, $now->diffInDays($checkIn, false));
    }

    /**
     * Aplicar política a una reserva al crearla
     */
    public function applyPolicyToReservation(Reservation $reservation)
    {
        // Si ya tiene una política asignada, no hacer nada
        if ($reservation->cancellation_policy_id) {
            $policy = CancellationPolicy::find($reservation->cancellation_policy_id);
            if ($policy) {
                $this->updateReservationDeadline($reservation, $policy);
                return $policy;
            }
        }

        // Buscar política aplicable
        $policy = CancellationPolicy::getApplicablePolicy(
            $reservation->room_type_id,
            $reservation->reservation_type
        );

        if ($policy) {
            $reservation->cancellation_policy_id = $policy->id;
            $this->updateReservationDeadline($reservation, $policy);
            $reservation->save();
        }

        return $policy;
    }

    /**
     * Actualizar fecha límite de cancelación en la reserva
     */
    protected function updateReservationDeadline(Reservation $reservation, CancellationPolicy $policy)
    {
        if ($policy->cancellation_days_before) {
            $deadline = $policy->calculateDeadline($reservation->check_in_date);
            $reservation->cancellation_deadline = $deadline;
        } else {
            $reservation->cancellation_deadline = null;
        }
    }

    /**
     * Procesar cancelación completa de una reserva
     */
    public function processCancellation(Reservation $reservation, $reason = null)
    {
        // Si es reserva principal de grupo, cancelar también las hijas
        if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            return $this->processGroupCancellation($reservation, $reason);
        }
        
        // Cancelación simple (reserva individual o hija)
        return $this->processSingleCancellation($reservation, $reason);
    }

    /**
     * Cancelar reserva grupal (principal + hijas)
     */
    protected function processGroupCancellation(Reservation $mainReservation, $reason = null)
    {
        DB::beginTransaction();
        try {
            // Cargar reservas hijas
            $mainReservation->load('childReservations');
            $childReservations = $mainReservation->childReservations;
            
            // Calcular reembolso total para todo el grupo
            $totalRefund = 0;
            $totalPenalty = 0;
            $totalPaid = 0;
            
            // Calcular para principal
            $mainRefund = $this->calculateRefund($mainReservation);
            $totalRefund += $mainRefund['refund_amount'];
            $totalPenalty += $mainRefund['penalty_amount'];
            $totalPaid += $mainRefund['total_paid'];
            
            // Calcular para hijas
            foreach ($childReservations as $child) {
                $childRefund = $this->calculateRefund($child);
                $totalRefund += $childRefund['refund_amount'];
                $totalPenalty += $childRefund['penalty_amount'];
                $totalPaid += $childRefund['total_paid'];
            }
            
            // Cancelar principal
            $mainReservation->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'refund_amount' => $mainRefund['refund_amount'],
                'penalty_amount' => $mainRefund['penalty_amount'],
            ]);
            
            if ($mainRefund['refund_amount'] > 0) {
                $mainReservation->payment_status = 'refunded';
            }
            $mainReservation->save();
            
            // Cancelar hijas
            foreach ($mainReservation->childReservations as $child) {
                $childRefund = $this->calculateRefund($child);
                $child->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'refund_amount' => $child->refund_amount + $childRefund['refund_amount'],
                    'penalty_amount' => $child->penalty_amount + $childRefund['penalty_amount'],
                ]);
                
                if ($childRefund['refund_amount'] > 0) {
                    $child->payment_status = 'refunded';
                }
                $child->save();
            }
            
            DB::commit();
            
            return [
                'refund_amount' => $totalRefund,
                'penalty_amount' => $totalPenalty,
                'total_paid' => $totalPaid,
                'can_refund' => $totalRefund > 0,
                'rooms_cancelled' => 1 + $mainReservation->childReservations->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Cancelación simple (reserva individual o hija)
     */
    protected function processSingleCancellation(Reservation $reservation, $reason = null)
    {
        $refundCalculation = $this->calculateRefund($reservation);

        $reservation->update([
            'refund_amount' => $refundCalculation['refund_amount'],
            'penalty_amount' => $refundCalculation['penalty_amount'],
            'cancellation_reason' => $reason,
        ]);

        if ($refundCalculation['refund_amount'] > 0) {
            $reservation->payment_status = 'refunded';
            $reservation->save();
        }

        return [
            'refund_amount' => $refundCalculation['refund_amount'],
            'penalty_amount' => $refundCalculation['penalty_amount'],
            'total_paid' => $refundCalculation['total_paid'],
            'can_refund' => $refundCalculation['refund_amount'] > 0,
            'policy' => $refundCalculation['policy'],
            'days_until_checkin' => $refundCalculation['days_until_checkin'],
            'before_deadline' => $refundCalculation['before_deadline'],
        ];
    }
}

