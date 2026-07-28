<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GuestPortalOtpService
{
    public function __construct(protected GuestPortalService $portalService)
    {
    }

    public function requestOtp(Reservation $reservation): array
    {
        $this->portalService->assertAccessible($reservation);

        $recipient = $this->portalService->getRecipient($reservation);
        if (!$recipient) {
            throw new \DomainException('No hay email del titular para enviar el código de verificación.');
        }

        $this->assertRequestRateLimit($reservation);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = (int) config('services.guest_portal.otp_ttl_minutes', 10);
        $otpHash = Hash::make($code);

        $reservation->update([
            'guest_portal_otp_hash' => $otpHash,
            'guest_portal_otp_expires_at' => now()->addMinutes($ttl),
            'guest_portal_otp_attempts' => 0,
        ]);

        $logoUrl = $this->getLogoUrl();

        try {
            Mail::send(
                'emails.guest_portal_otp',
                [
                    'reservation' => $reservation,
                    'recipient' => $recipient,
                    'otp_code' => $code,
                    'ttl_minutes' => $ttl,
                    'logoUrl' => $logoUrl,
                ],
                function ($message) use ($recipient, $reservation) {
                    $message->to($recipient['email'], $recipient['name'])
                        ->subject('Código de verificación - Reserva #' . $reservation->reservation_number);
                }
            );
        } catch (\Throwable $e) {
            $reservation->update([
                'guest_portal_otp_hash' => null,
                'guest_portal_otp_expires_at' => null,
                'guest_portal_otp_attempts' => 0,
            ]);
            Log::error('Guest portal OTP mail failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            throw new \DomainException('No se pudo enviar el código de verificación. Intente de nuevo más tarde.');
        }

        Log::info('Guest portal OTP sent', [
            'reservation_id' => $reservation->id,
            'email' => $recipient['email'],
        ]);

        return [
            'masked_email' => $this->portalService->maskEmail($recipient['email']),
            'expires_in_minutes' => $ttl,
        ];
    }

    public function verifyOtp(Reservation $reservation, string $otpCode): array
    {
        $this->portalService->assertAccessible($reservation);

        if (!$reservation->guest_portal_otp_hash) {
            return ['valid' => false, 'message' => 'No hay código OTP solicitado. Solicite uno nuevo.'];
        }

        $maxAttempts = (int) config('services.guest_portal.otp_max_attempts', 5);
        if ((int) $reservation->guest_portal_otp_attempts >= $maxAttempts) {
            return [
                'valid' => false,
                'message' => 'Se alcanzó el máximo de intentos. Solicite un código nuevo.',
                'locked' => true,
            ];
        }

        if (
            $reservation->guest_portal_otp_expires_at
            && now()->gt($reservation->guest_portal_otp_expires_at)
        ) {
            return ['valid' => false, 'message' => 'El código OTP ha expirado. Solicite uno nuevo.'];
        }

        if (!Hash::check($otpCode, $reservation->guest_portal_otp_hash)) {
            $reservation->increment('guest_portal_otp_attempts');
            $remaining = max(0, $maxAttempts - ((int) $reservation->fresh()->guest_portal_otp_attempts));

            return [
                'valid' => false,
                'message' => $remaining > 0
                    ? "Código OTP incorrecto. Le quedan {$remaining} intento(s)."
                    : 'Se alcanzó el máximo de intentos. Solicite un código nuevo.',
                'locked' => $remaining === 0,
            ];
        }

        $sessionToken = $this->portalService->issueSession($reservation);

        return [
            'valid' => true,
            'session_token' => $sessionToken,
            'expires_in_hours' => (int) config('services.guest_portal.session_ttl_hours', 2),
        ];
    }

    protected function assertRequestRateLimit(Reservation $reservation): void
    {
        $limit = (int) config('services.guest_portal.otp_request_limit', 3);
        $window = (int) config('services.guest_portal.otp_request_window_minutes', 10);
        $key = 'guest_portal_otp_req:' . $reservation->guest_portal_token;

        // increment es atómico en drivers soportados; si la key no existe, add + increment.
        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->addMinutes($window));
        }

        $count = (int) Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, now()->addMinutes($window));
        }

        if ($count > $limit) {
            throw new \DomainException(
                "Ha solicitado demasiados códigos. Espere {$window} minutos e intente de nuevo."
            );
        }
    }

    protected function getLogoUrl(): ?string
    {
        foreach ([
            public_path('logocv.png') => url('logocv.png'),
            public_path('images/logocv.png') => url('images/logocv.png'),
            storage_path('app/public/logocv.png') => url('storage/logocv.png'),
        ] as $path => $url) {
            if (file_exists($path)) {
                return $url;
            }
        }

        return null;
    }
}
