<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReservationEmailService
{
    protected $certificateService;

    public function __construct(ReservationCertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function sendReservationConfirmation(Reservation $reservation)
    {
        $reservation->loadMissing(['customer', 'guests']);

        $certificate = $this->certificateService->generateCertificate($reservation);

        $recipients = [];

        // Cliente
        if ($reservation->customer && $reservation->customer->email) {
            $customerName = $reservation->customer->customer_type === 'company'
                ? $reservation->customer->company_name
                : $reservation->customer->display_name;

            $recipients[] = [
                'email' => $reservation->customer->email,
                'name' => $customerName
            ];
        }

        // Huésped principal
        $primaryGuest = $reservation->guests()->where('is_primary_guest', true)->first();
        if ($primaryGuest && $primaryGuest->email) {
            if (!$reservation->customer || $primaryGuest->email !== $reservation->customer->email) {
                $recipients[] = [
                    'email' => $primaryGuest->email,
                    'name' => $primaryGuest->full_name
                ];
            }
        }

        // Correo interno
        $internalEmail = env('RESERVATION_INTERNAL_EMAIL', env('MAIL_FROM_ADDRESS'));
        if ($internalEmail) {
            $recipients[] = [
                'email' => $internalEmail,
                'name' => 'Campo Verde - Reservas'
            ];
        }

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar el certificado de reserva #{$reservation->reservation_number}");
            return;
        }

        try {
            Mail::send(
                'emails.reservation_confirmation',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                ],
                function ($message) use ($reservation, $certificate, $recipients) {
                    $subject = "Confirmación de Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                        }
                    }

                    $message->subject($subject)
                        ->attach(Storage::path($certificate['path']), [
                            'as' => $certificate['filename'],
                            'mime' => 'application/pdf',
                        ]);
                }
            );

            $reservation->update([
                'email_sent' => true,
                'email_sent_at' => now()
            ]);

            Log::info("Certificado de reserva #{$reservation->reservation_number} enviado a " . count($recipients) . " destinatario(s)");
        } catch (\Exception $e) {
            Log::error("Error enviando certificado de reserva #{$reservation->reservation_number}: " . $e->getMessage());
            throw $e;
        }
    }
}



