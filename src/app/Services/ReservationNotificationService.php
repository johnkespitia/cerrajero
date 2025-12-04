<?php

namespace App\Services;

use App\Models\Reservation;
use App\Services\ReservationEmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReservationNotificationService
{
    protected $emailService;

    public function __construct(ReservationEmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Enviar recordatorio 24 horas antes del check-in
     */
    public function sendCheckInReminder(Reservation $reservation)
    {
        if ($reservation->check_in_reminder_sent) {
            return false;
        }

        $checkInDate = Carbon::parse($reservation->check_in_date);
        $now = Carbon::now();

        // Solo enviar si está a 24 horas o menos del check-in
        if ($checkInDate->diffInHours($now) <= 24 && $checkInDate->isFuture()) {
            try {
                $this->emailService->sendCheckInReminder($reservation);
                $reservation->update([
                    'check_in_reminder_sent' => true,
                    'check_in_reminder_sent_at' => now(),
                ]);
                return true;
            } catch (\Exception $e) {
                Log::error('Error sending check-in reminder: ' . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Enviar confirmación de check-in exitoso
     */
    public function sendCheckInConfirmation(Reservation $reservation)
    {
        try {
            $this->emailService->sendCheckInConfirmation($reservation);
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending check-in confirmation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar recordatorio de check-out
     */
    public function sendCheckOutReminder(Reservation $reservation)
    {
        if ($reservation->reminder_sent) {
            return false;
        }

        $checkOutDate = Carbon::parse($reservation->check_out_date);
        $now = Carbon::now();

        // Enviar el día del check-out
        if ($checkOutDate->isToday()) {
            try {
                $this->emailService->sendCheckOutReminder($reservation);
                $reservation->update([
                    'reminder_sent' => true,
                    'reminder_sent_at' => now(),
                ]);
                return true;
            } catch (\Exception $e) {
                Log::error('Error sending check-out reminder: ' . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Enviar notificación de cancelación
     */
    public function sendCancellationNotification(Reservation $reservation)
    {
        try {
            $this->emailService->sendCancellationNotification($reservation);
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending cancellation notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de cambios en la reserva
     */
    public function sendReservationUpdateNotification(Reservation $reservation, $changes = [])
    {
        try {
            $this->emailService->sendReservationUpdateNotification($reservation, $changes);
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending reservation update notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Procesar recordatorios pendientes (para usar en un job/scheduled task)
     */
    public function processPendingReminders()
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // Recordatorios de check-in (24h antes)
        $checkInReminders = Reservation::where('status', 'confirmed')
            ->where('check_in_reminder_sent', false)
            ->whereDate('check_in_date', $tomorrow)
            ->get();

        foreach ($checkInReminders as $reservation) {
            $this->sendCheckInReminder($reservation);
        }

        // Recordatorios de check-out (el mismo día)
        $checkOutReminders = Reservation::where('status', 'checked_in')
            ->where('reminder_sent', false)
            ->whereDate('check_out_date', $today)
            ->get();

        foreach ($checkOutReminders as $reservation) {
            $this->sendCheckOutReminder($reservation);
        }
    }
}

