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
            // Verificar que el archivo del certificado existe
            if (!Storage::exists($certificate['path'])) {
                Log::error("El archivo del certificado no existe: {$certificate['path']}");
                throw new \Exception("El archivo del certificado no existe");
            }

            // Log de configuración SMTP (sin contraseña)
            Log::info("Intentando enviar email de confirmación #{$reservation->reservation_number}", [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from' => config('mail.from.address'),
                'recipients_count' => count($recipients)
            ]);

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
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $certificatePath = Storage::path($certificate['path']);
                    if (!file_exists($certificatePath)) {
                        Log::error("El archivo del certificado no existe en: {$certificatePath}");
                        throw new \Exception("El archivo del certificado no existe");
                    }

                    $message->subject($subject)
                        ->attach($certificatePath, [
                            'as' => $certificate['filename'],
                            'mime' => 'application/pdf',
                        ]);
                }
            );

            $reservation->update([
                'email_sent' => true,
                'email_sent_at' => now()
            ]);

            Log::info("Certificado de reserva #{$reservation->reservation_number} enviado exitosamente a " . count($recipients) . " destinatario(s)");
            Log::info("Destinatarios:", $recipients);
        } catch (\Swift_TransportException $e) {
            Log::error("Error de transporte SMTP al enviar certificado de reserva #{$reservation->reservation_number}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        } catch (\Exception $e) {
            Log::error("Error enviando certificado de reserva #{$reservation->reservation_number}: " . $e->getMessage());
            Log::error("Tipo de excepción: " . get_class($e));
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Enviar email de confirmación de checkout con PDF
     */
    public function sendCheckoutConfirmation(Reservation $reservation, array $certificate, array $invoice = null)
    {
        $reservation->loadMissing(['customer', 'guests']);

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
            Log::warning("No hay destinatarios para enviar el certificado de checkout #{$reservation->reservation_number}");
            return;
        }

        try {
            // Verificar que el archivo del certificado existe
            if (!Storage::exists($certificate['path'])) {
                Log::error("El archivo del certificado no existe: {$certificate['path']}");
                throw new \Exception("El archivo del certificado no existe");
            }

            // Log de configuración SMTP (sin contraseña)
            Log::info("Intentando enviar email de checkout #{$reservation->reservation_number}", [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from' => config('mail.from.address'),
                'recipients_count' => count($recipients)
            ]);

            Mail::send(
                'emails.checkout_confirmation',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                ],
                function ($message) use ($reservation, $certificate, $invoice, $recipients) {
                    $subject = "Check-out Completado - Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $certificatePath = Storage::path($certificate['path']);
                    if (!file_exists($certificatePath)) {
                        Log::error("El archivo del certificado no existe en: {$certificatePath}");
                        throw new \Exception("El archivo del certificado no existe");
                    }

                    $message->subject($subject)
                        ->attach($certificatePath, [
                            'as' => $certificate['filename'],
                            'mime' => 'application/pdf',
                        ]);

                    // Adjuntar factura consolidada si existe
                    if ($invoice && isset($invoice['path'])) {
                        $invoicePath = Storage::path($invoice['path']);
                        if (file_exists($invoicePath)) {
                            $message->attach($invoicePath, [
                                'as' => $invoice['filename'],
                                'mime' => 'application/pdf',
                            ]);
                            Log::info("Factura consolidada adjuntada: {$invoice['filename']}");
                        }
                    }
                }
            );

            Log::info("Certificado de checkout #{$reservation->reservation_number} enviado exitosamente a " . count($recipients) . " destinatario(s)");
            Log::info("Destinatarios:", $recipients);
        } catch (\Swift_TransportException $e) {
            Log::error("Error de transporte SMTP al enviar certificado de checkout #{$reservation->reservation_number}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        } catch (\Exception $e) {
            Log::error("Error enviando certificado de checkout #{$reservation->reservation_number}: " . $e->getMessage());
            Log::error("Tipo de excepción: " . get_class($e));
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Obtener lista de destinatarios para un email
     */
    protected function getRecipients(Reservation $reservation)
    {
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

        return $recipients;
    }

    /**
     * Enviar recordatorio 24 horas antes del check-in
     */
    public function sendCheckInReminder(Reservation $reservation)
    {
        $reservation->loadMissing(['customer', 'guests', 'room', 'roomType']);

        $recipients = $this->getRecipients($reservation);

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar recordatorio de check-in #{$reservation->reservation_number}");
            return;
        }

        try {
            Log::info("Intentando enviar recordatorio de check-in #{$reservation->reservation_number}", [
                'recipients_count' => count($recipients)
            ]);

            Mail::send(
                'emails.check_in_reminder',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                ],
                function ($message) use ($reservation, $recipients) {
                    $subject = "Recordatorio de Check-in - Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $message->subject($subject);
                }
            );

            Log::info("Recordatorio de check-in #{$reservation->reservation_number} enviado exitosamente a " . count($recipients) . " destinatario(s)");
        } catch (\Exception $e) {
            Log::error("Error enviando recordatorio de check-in #{$reservation->reservation_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar confirmación de check-in exitoso
     */
    public function sendCheckInConfirmation(Reservation $reservation)
    {
        $reservation->loadMissing(['customer', 'guests', 'room', 'roomType']);

        $recipients = $this->getRecipients($reservation);

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar confirmación de check-in #{$reservation->reservation_number}");
            return;
        }

        try {
            Log::info("Intentando enviar confirmación de check-in #{$reservation->reservation_number}", [
                'recipients_count' => count($recipients)
            ]);

            Mail::send(
                'emails.check_in_confirmation',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                ],
                function ($message) use ($reservation, $recipients) {
                    $subject = "Check-in Confirmado - Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $message->subject($subject);
                }
            );

            Log::info("Confirmación de check-in #{$reservation->reservation_number} enviada exitosamente a " . count($recipients) . " destinatario(s)");
        } catch (\Exception $e) {
            Log::error("Error enviando confirmación de check-in #{$reservation->reservation_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar recordatorio de check-out
     */
    public function sendCheckOutReminder(Reservation $reservation)
    {
        $reservation->loadMissing(['customer', 'guests', 'room', 'roomType']);

        $recipients = $this->getRecipients($reservation);

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar recordatorio de check-out #{$reservation->reservation_number}");
            return;
        }

        try {
            Log::info("Intentando enviar recordatorio de check-out #{$reservation->reservation_number}", [
                'recipients_count' => count($recipients)
            ]);

            Mail::send(
                'emails.check_out_reminder',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                ],
                function ($message) use ($reservation, $recipients) {
                    $subject = "Recordatorio de Check-out - Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $message->subject($subject);
                }
            );

            Log::info("Recordatorio de check-out #{$reservation->reservation_number} enviado exitosamente a " . count($recipients) . " destinatario(s)");
        } catch (\Exception $e) {
            Log::error("Error enviando recordatorio de check-out #{$reservation->reservation_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar notificación de cancelación
     */
    public function sendCancellationNotification(Reservation $reservation)
    {
        $reservation->loadMissing(['customer', 'guests', 'room', 'roomType']);

        $recipients = $this->getRecipients($reservation);

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar notificación de cancelación #{$reservation->reservation_number}");
            return;
        }

        try {
            Log::info("Intentando enviar notificación de cancelación #{$reservation->reservation_number}", [
                'recipients_count' => count($recipients)
            ]);

            Mail::send(
                'emails.cancellation_notification',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                ],
                function ($message) use ($reservation, $recipients) {
                    $subject = "Cancelación de Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $message->subject($subject);
                }
            );

            Log::info("Notificación de cancelación #{$reservation->reservation_number} enviada exitosamente a " . count($recipients) . " destinatario(s)");
        } catch (\Exception $e) {
            Log::error("Error enviando notificación de cancelación #{$reservation->reservation_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar notificación de cambios en la reserva
     */
    public function sendReservationUpdateNotification(Reservation $reservation, array $changes = [])
    {
        $reservation->loadMissing(['customer', 'guests', 'room', 'roomType']);

        $recipients = $this->getRecipients($reservation);

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar notificación de actualización #{$reservation->reservation_number}");
            return;
        }

        try {
            Log::info("Intentando enviar notificación de actualización #{$reservation->reservation_number}", [
                'recipients_count' => count($recipients),
                'changes' => array_keys($changes)
            ]);

            Mail::send(
                'emails.reservation_update',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                    'changes' => $changes,
                ],
                function ($message) use ($reservation, $recipients) {
                    $subject = "Actualización de Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $message->subject($subject);
                }
            );

            Log::info("Notificación de actualización #{$reservation->reservation_number} enviada exitosamente a " . count($recipients) . " destinatario(s)");
        } catch (\Exception $e) {
            Log::error("Error enviando notificación de actualización #{$reservation->reservation_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar confirmación de pago registrado
     */
    public function sendPaymentConfirmation(
        Reservation $reservation,
        \App\Models\ReservationPayment $payment,
        $pendingKioskInvoices,
        $totalPaid,
        $totalDue,
        $newBalance
    ) {
        $reservation->loadMissing(['customer', 'guests', 'room', 'roomType', 'additionalServices.additionalService']);

        $recipients = $this->getRecipients($reservation);

        if (empty($recipients)) {
            Log::warning("No hay destinatarios para enviar confirmación de pago #{$reservation->reservation_number}");
            return;
        }

        try {
            Log::info("Intentando enviar confirmación de pago #{$reservation->reservation_number}", [
                'recipients_count' => count($recipients),
                'payment_amount' => $payment->amount
            ]);

            Mail::send(
                'emails.payment_confirmation',
                [
                    'reservation' => $reservation,
                    'customer' => $reservation->customer,
                    'payment' => $payment,
                    'pendingKioskInvoices' => $pendingKioskInvoices,
                    'totalPaid' => $totalPaid,
                    'totalDue' => $totalDue,
                    'newBalance' => $newBalance,
                ],
                function ($message) use ($reservation, $recipients, $payment) {
                    $subject = "Confirmación de Pago - Reserva #{$reservation->reservation_number}";

                    foreach ($recipients as $recipient) {
                        if (empty($message->getTo())) {
                            $message->to($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario TO: {$recipient['email']}");
                        } else {
                            $message->cc($recipient['email'], $recipient['name']);
                            Log::info("Agregando destinatario CC: {$recipient['email']}");
                        }
                    }

                    $message->subject($subject);
                }
            );

            Log::info("Confirmación de pago #{$reservation->reservation_number} enviada exitosamente a " . count($recipients) . " destinatario(s)");
        } catch (\Swift_TransportException $e) {
            Log::error("Error de transporte SMTP al enviar confirmación de pago #{$reservation->reservation_number}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            // No lanzar excepción para no interrumpir el flujo del pago
        } catch (\Exception $e) {
            Log::error("Error enviando confirmación de pago #{$reservation->reservation_number}: " . $e->getMessage());
            Log::error("Tipo de excepción: " . get_class($e));
            Log::error("Stack trace: " . $e->getTraceAsString());
            // No lanzar excepción para no interrumpir el flujo del pago
        }
    }
}



