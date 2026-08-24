<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Services\ReservationEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reservationId;
    public int $paymentId;
    public int $tries = 3;
    public int $backoff = 15;
    public int $timeout = 90;
    public int $maxExceptions = 3;

    public function __construct(int $reservationId, int $paymentId)
    {
        $this->reservationId = $reservationId;
        $this->paymentId = $paymentId;
        $this->onQueue('emails');
    }

    public function handle(ReservationEmailService $emailService): void
    {
        try {
            try { DB::connection()->getPdo(); } catch (\Throwable $e) { DB::reconnect(); }

            $reservation = Reservation::with(['customer'])->find($this->reservationId);
            $payment = ReservationPayment::with('paymentType')->find($this->paymentId);

            if (!$reservation || !$payment) {
                Log::warning("SendPaymentConfirmationJob: reserva {$this->reservationId} o pago {$this->paymentId} no encontrado");
                return;
            }

            // Recalcular totales del grupo igual que en ReservationController::addPayment
            $mainReservation = $reservation->parent_reservation_id ? $reservation->parentReservation : $reservation;
            if ($mainReservation) {
                $mainReservation->load('childReservations');
            }
            $groupReservationIds = $reservation->parent_reservation_id
                ? $reservation->allGroupReservations()->pluck('id')->toArray()
                : array_merge([$mainReservation->id], $mainReservation->childReservations->pluck('id')->toArray());

            $groupReservations = \App\Models\Reservation::whereIn('id', $groupReservationIds)->get();
            $finalPrice = $groupReservations->sum(fn($r) => (float) ($r->final_price ?? $r->total_price ?? 0));

            $pendingKioskInvoices = \App\Models\KioskInvoice::whereIn('reservation_id', $groupReservationIds)
                ->whereHas('payment_type', fn($q) => $q->where('credit', true))
                ->where('payed', false)
                ->whereNull('cancelled_at')
                ->with(['details.kiosk_unit.product', 'payment_type'])
                ->get();

            $totalPendingKiosk = $pendingKioskInvoices->sum(fn($inv) => $inv->details->sum('price'));

            $groupTotalPaid = \App\Models\ReservationPayment::whereIn('reservation_id', $groupReservationIds)
                ->where(function ($q) { $q->where('concept', '!=', 'Compra en kiosko (a crédito)')->orWhereNull('concept'); })
                ->sum('amount');

            $totalDue = $finalPrice + $totalPendingKiosk;
            $newBalance = max(0, $totalDue - $groupTotalPaid);

            $targetReservation = $mainReservation ?? $reservation;
            $emailService->sendPaymentConfirmation(
                $targetReservation->fresh(['customer']),
                $payment,
                $pendingKioskInvoices,
                $groupTotalPaid,
                $totalDue,
                $newBalance
            );

            Log::info("SendPaymentConfirmationJob completado reserva #{$reservation->reservation_number} pago {$this->paymentId}");
        } catch (\Throwable $e) {
            Log::error("SendPaymentConfirmationJob fallo reserva {$this->reservationId} pago {$this->paymentId}: ".$e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendPaymentConfirmationJob fallo definitivo reserva {$this->reservationId} pago {$this->paymentId}: ".$e->getMessage());
    }
}
