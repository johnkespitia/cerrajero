<?php

namespace App\Services;

use App\Models\KioskInvoice;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KioskOtpService
{
    /**
     * Generar y enviar OTP para compra a crédito
     */
    public function generateAndSendOtp(KioskInvoice $invoice, Reservation $reservation)
    {
        // Generar código OTP de 6 dígitos
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Guardar OTP en la factura
        $invoice->update([
            'otp_code' => $otpCode,
            'otp_sent_at' => now(),
            'otp_expires_at' => now()->addMinutes(10) // OTP válido por 10 minutos
        ]);

        // Obtener destinatario (huésped principal o cliente)
        $recipient = $this->getRecipient($reservation);
        
        if (!$recipient) {
            Log::warning("No se pudo enviar OTP para factura #{$invoice->id}: No hay email disponible");
            throw new \Exception('No se encontró email del huésped principal o cliente para enviar el OTP');
        }

        try {
            // Obtener URL del logo para el email (URL directa, no base64)
            $logoUrl = $this->getLogoUrl();
            
            // Enviar email con OTP
            Mail::send(
                'emails.kiosk_otp',
                [
                    'invoice' => $invoice,
                    'reservation' => $reservation,
                    'otp_code' => $otpCode,
                    'recipient' => $recipient,
                    'logoUrl' => $logoUrl,
                ],
                function ($message) use ($recipient, $invoice) {
                    $message->to($recipient['email'], $recipient['name'])
                        ->subject("Código de verificación para compra - Factura #{$invoice->payment_code}");
                }
            );

            Log::info("OTP enviado para factura #{$invoice->id} a {$recipient['email']}");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error enviando OTP para factura #{$invoice->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar OTP
     */
    public function verifyOtp(KioskInvoice $invoice, string $otpCode, $verifiedByUserId)
    {
        // Verificar que el OTP existe
        if (!$invoice->otp_code) {
            return [
                'valid' => false,
                'message' => 'No hay código OTP generado para esta factura'
            ];
        }

        // Verificar que no haya expirado
        if ($invoice->otp_expires_at && now()->gt($invoice->otp_expires_at)) {
            return [
                'valid' => false,
                'message' => 'El código OTP ha expirado. Por favor solicite uno nuevo.'
            ];
        }

        // Verificar que no haya sido usado
        if ($invoice->otp_verified_at) {
            return [
                'valid' => false,
                'message' => 'Este código OTP ya fue utilizado'
            ];
        }

        // Verificar el código
        if ($invoice->otp_code !== $otpCode) {
            return [
                'valid' => false,
                'message' => 'Código OTP incorrecto'
            ];
        }

        // Marcar como verificado
        $invoice->update([
            'otp_verified_at' => now(),
            'otp_verified_by' => $verifiedByUserId
        ]);

        Log::info("OTP verificado para factura #{$invoice->id} por usuario #{$verifiedByUserId}");

        return [
            'valid' => true,
            'message' => 'Código OTP verificado correctamente'
        ];
    }

    /**
     * Obtener destinatario del email (huésped principal o cliente)
     */
    protected function getRecipient(Reservation $reservation)
    {
        // Prioridad 1: Huésped principal
        $primaryGuest = $reservation->guests()->where('is_primary_guest', true)->first();
        if ($primaryGuest && $primaryGuest->email) {
            return [
                'email' => $primaryGuest->email,
                'name' => $primaryGuest->full_name
            ];
        }

        // Prioridad 2: Cliente
        if ($reservation->customer && $reservation->customer->email) {
            $customerName = $reservation->customer->customer_type === 'company'
                ? $reservation->customer->company_name
                : ($reservation->customer->name . ' ' . $reservation->customer->last_name);
            
            return [
                'email' => $reservation->customer->email,
                'name' => trim($customerName)
            ];
        }

        return null;
    }

    /**
     * Obtener URL del logo para usar en emails
     * Usa URL directa (no base64) para mejor compatibilidad con clientes de email
     * 
     * @return string|null URL del logo o null si no se encuentra
     */
    protected function getLogoUrl(): ?string
    {
        // Intentar primero con storage (requiere symlink)
        $logoPath = storage_path('app/public/logocv.png');
        if (file_exists($logoPath)) {
            $logoUrl = url('storage/logocv.png');
            Log::info("Logo URL generada desde storage: {$logoUrl}");
            return $logoUrl;
        }
        
        // Fallback: buscar en public directamente (no requiere symlink)
        $publicLogoPath = public_path('logocv.png');
        if (file_exists($publicLogoPath)) {
            $logoUrl = url('logocv.png');
            Log::info("Logo URL generada desde public: {$logoUrl}");
            return $logoUrl;
        }
        
        // Fallback adicional: buscar en public/images
        $publicImagesLogoPath = public_path('images/logocv.png');
        if (file_exists($publicImagesLogoPath)) {
            $logoUrl = url('images/logocv.png');
            Log::info("Logo URL generada desde public/images: {$logoUrl}");
            return $logoUrl;
        }
        
        Log::warning("Logo NO encontrado en ninguna ubicación esperada");
        return null;
    }
}

