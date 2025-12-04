<?php

namespace App\Services;

use App\Models\ReservationAudit;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationAuditService
{
    public function log($action, Reservation $reservation, $oldValues = null, $newValues = null, $notes = null, $userId = null, Request $request = null)
    {
        return ReservationAudit::create([
            'reservation_id' => $reservation->id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'old_values' => $oldValues ? $this->sanitizeValues($oldValues) : null,
            'new_values' => $newValues ? $this->sanitizeValues($newValues) : null,
            'notes' => $notes,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
        ]);
    }

    protected function sanitizeValues($values)
    {
        if (is_array($values)) {
            // Remover campos sensibles si es necesario
            unset($values['password'], $values['token'], $values['api_key']);
            return $values;
        }
        return $values;
    }

    public function logCreation(Reservation $reservation, Request $request = null)
    {
        return $this->log(
            'created',
            $reservation,
            null,
            $reservation->toArray(),
            'Reserva creada',
            auth()->id(),
            $request
        );
    }

    public function logUpdate(Reservation $reservation, $oldValues, $newValues, Request $request = null)
    {
        // Solo registrar cambios
        $changes = [];
        foreach ($newValues as $key => $value) {
            if (!isset($oldValues[$key]) || $oldValues[$key] != $value) {
                $changes[$key] = [
                    'old' => $oldValues[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        if (empty($changes)) {
            return null;
        }

        return $this->log(
            'updated',
            $reservation,
            $oldValues,
            $newValues,
            'Reserva actualizada: ' . implode(', ', array_keys($changes)),
            auth()->id(),
            $request
        );
    }

    public function logStatusChange(Reservation $reservation, $oldStatus, $newStatus, $notes = null, Request $request = null)
    {
        return $this->log(
            'status_changed',
            $reservation,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            $notes ?? "Estado cambiado de {$oldStatus} a {$newStatus}",
            auth()->id(),
            $request
        );
    }

    public function logPayment(Reservation $reservation, $amount, $paymentMethod, $notes = null, Request $request = null)
    {
        return $this->log(
            'payment_added',
            $reservation,
            null,
            ['amount' => $amount, 'payment_method' => $paymentMethod],
            $notes ?? "Pago registrado: {$amount} via {$paymentMethod}",
            auth()->id(),
            $request
        );
    }

    public function logCancellation(Reservation $reservation, $reason = null, Request $request = null)
    {
        return $this->log(
            'cancelled',
            $reservation,
            ['status' => $reservation->getOriginal('status')],
            ['status' => 'cancelled', 'cancellation_reason' => $reason],
            $reason ?? 'Reserva cancelada',
            auth()->id(),
            $request
        );
    }
}

