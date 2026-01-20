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
            // Obtener logo en base64 para el email
            $logoBase64 = $this->getLogoBase64();
            
            // Enviar email con OTP
            Mail::send(
                'emails.kiosk_otp',
                [
                    'invoice' => $invoice,
                    'reservation' => $reservation,
                    'otp_code' => $otpCode,
                    'recipient' => $recipient,
                    'logo_base64' => $logoBase64,
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
     * Obtener logo en formato base64 para usar en emails
     * Prioriza la ruta constante: storage/app/public/logocv.png
     * 
     * @return string|null Logo en formato base64 o null si no se encuentra
     */
    protected function getLogoBase64(): ?string
    {
        // Ruta constante del logo (prioridad)
        $logoPath = storage_path('app/public/logocv.png');
        
        if (file_exists($logoPath)) {
            $imageData = file_get_contents($logoPath);
            $imageInfo = getimagesize($logoPath);
            $mimeType = $imageInfo['mime'] ?? 'image/png';
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        }
        
        // Fallback: buscar en otras ubicaciones posibles
        $possibleLogoPaths = [
            storage_path('app/public/logo-campo-verde.png'),
            public_path('images/logo-campo-verde.png'),
            public_path('logo.png'),
            public_path('logo.jpg'),
            storage_path('app/public/logo.png'),
            base_path('public/images/logo-campo-verde.png'),
            base_path('public/logo.png'),
        ];

        foreach ($possibleLogoPaths as $path) {
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                $imageInfo = getimagesize($path);
                $mimeType = $imageInfo['mime'] ?? 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        }
        
        return null;
    }
}

