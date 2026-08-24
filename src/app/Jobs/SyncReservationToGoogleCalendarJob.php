<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncReservationToGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reservationId;
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(int $reservationId)
    {
        $this->reservationId = $reservationId;
        $this->onQueue('calendar');
    }

    public function handle(GoogleCalendarService $calendarService): void
    {
        try {
            try { DB::connection()->getPdo(); } catch (\Throwable $e) { DB::reconnect(); }

            $reservation = Reservation::find($this->reservationId);
            if (!$reservation) {
                Log::warning("SyncReservationToGoogleCalendarJob: reserva {$this->reservationId} no encontrada");
                return;
            }

            $calendarService->syncReservation($reservation);
            Log::info("Google Calendar sync encolado completado para reserva #{$reservation->reservation_number}");
        } catch (\Throwable $e) {
            Log::error("SyncReservationToGoogleCalendarJob fallo reserva {$this->reservationId}: ".$e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SyncReservationToGoogleCalendarJob fallo definitivo reserva {$this->reservationId}: ".$e->getMessage());
    }
}
