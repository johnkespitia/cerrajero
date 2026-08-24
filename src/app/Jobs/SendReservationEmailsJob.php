<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\ReservationCertificateService;
use App\Services\ReservationEmailService;
use App\Services\ReservationNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendReservationEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reservationId;
    public string $type;
    public array $payload;
    public int $tries = 3;
    public int $backoff = 10;

    /**
     * @param int $reservationId
     * @param string $type one of: confirmation, checkout, check_in, cancellation, update
     * @param array $payload extra data (changes, etc)
     */
    public function __construct(int $reservationId, string $type, array $payload = [])
    {
        $this->reservationId = $reservationId;
        $this->type = $type;
        $this->payload = $payload;
        $this->onQueue('emails');
    }

    public function handle(
        ReservationEmailService $emailService,
        ReservationNotificationService $notificationService,
        ReservationCertificateService $certificateService
    ): void {
        try {
            try { DB::connection()->getPdo(); } catch (\Throwable $e) { DB::reconnect(); }

            $reservation = Reservation::find($this->reservationId);
            if (!$reservation) {
                Log::warning("SendReservationEmailsJob: reserva {$this->reservationId} no encontrada type {$this->type}");
                return;
            }

            match ($this->type) {
                'confirmation' => $emailService->sendReservationConfirmation($reservation->fresh()),
                'checkout' => $this->handleCheckout($reservation, $emailService, $certificateService),
                'check_in' => $notificationService->sendCheckInConfirmation($reservation->fresh()),
                'cancellation' => $notificationService->sendCancellationNotification($reservation->fresh()),
                'update' => $notificationService->sendReservationUpdateNotification($reservation->fresh(), $this->payload['changes'] ?? []),
                default => Log::warning("SendReservationEmailsJob tipo desconocido {$this->type}")
            };

            Log::info("SendReservationEmailsJob {$this->type} completado reserva #{$reservation->reservation_number}");
        } catch (\Throwable $e) {
            Log::error("SendReservationEmailsJob {$this->type} fallo reserva {$this->reservationId}: ".$e->getMessage());
            throw $e;
        }
    }

    protected function handleCheckout(Reservation $reservation, ReservationEmailService $emailService, ReservationCertificateService $certificateService): void
    {
        try {
            $certificate = $certificateService->generateCheckoutCertificate($reservation->fresh());
        } catch (\Throwable $e) {
            Log::warning("No se pudo generar certificado checkout job {$this->reservationId}: ".$e->getMessage());
            $certificate = null;
        }

        $invoice = null;
        try {
            $invoice = $certificateService->generateCheckoutInvoice($reservation->fresh());
        } catch (\Throwable $e) {
            Log::warning("No se pudo generar factura checkout job {$this->reservationId}: ".$e->getMessage());
        }

        if ($certificate) {
            $emailService->sendCheckoutConfirmation($reservation->fresh(), $certificate, $invoice);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendReservationEmailsJob {$this->type} fallo definitivo reserva {$this->reservationId}: ".$e->getMessage());
    }
}
