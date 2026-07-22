<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncReservationsToGoogleCalendar extends Command
{
    protected $signature = 'google-calendar:sync-reservations
                            {--dry-run : Listar reservas sin crear eventos}
                            {--from= : Fecha mínima de check-in (Y-m-d)}
                            {--to= : Fecha máxima de check-in (Y-m-d)}
                            {--id= : Sincronizar una reserva específica por ID}
                            {--include-cancelled : Incluir reservas canceladas}';

    protected $description = 'Crea eventos en Google Calendar para reservas que no tienen google_calendar_event_id';

    public function handle(GoogleCalendarService $calendarService): int
    {
        if (!$calendarService->isConfigured()) {
            $this->error('Google Calendar no está configurado o está inactivo.');
            return self::FAILURE;
        }

        $query = Reservation::query()
            ->whereNull('google_calendar_event_id')
            ->with(['customer', 'room', 'roomType', 'guests']);

        if (!$this->option('include-cancelled')) {
            $query->where('status', '!=', 'cancelled');
        }

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if ($from = $this->option('from')) {
            $query->whereDate('check_in_date', '>=', $from);
        }

        if ($to = $this->option('to')) {
            $query->whereDate('check_in_date', '<=', $to);
        }

        $reservations = $query
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get();

        if ($reservations->isEmpty()) {
            $this->info('No hay reservas pendientes de sincronizar.');
            return self::SUCCESS;
        }

        $this->info("Reservas pendientes: {$reservations->count()}");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Número', 'Check-in', 'Estado', 'Cliente'],
                $reservations->map(fn (Reservation $r) => [
                    $r->id,
                    $r->reservation_number,
                    $r->check_in_date?->format('Y-m-d'),
                    $r->status,
                    $r->customer?->display_name ?? '—',
                ])
            );
            $this->comment('Ejecuta sin --dry-run para crear los eventos.');
            return self::SUCCESS;
        }

        $created = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($reservations->count());
        $bar->start();

        foreach ($reservations as $reservation) {
            try {
                $calendarService->createEvent($reservation);
                $reservation->refresh();

                if ($reservation->google_calendar_event_id) {
                    $created++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("Reserva #{$reservation->id} ({$reservation->reservation_number}): no se creó evento (sin error explícito).");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Error syncing reservation to Google Calendar', [
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'message' => $e->getMessage(),
                ]);
                $this->newLine();
                $this->error("Reserva #{$reservation->id} ({$reservation->reservation_number}): {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Sincronización completada: {$created} creados, {$failed} fallidos.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
