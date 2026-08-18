<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GuestPortalService
{
    public const BLOCKED_STATUSES = ['checked_in', 'checked_out', 'cancelled'];

    public function ensureToken(Reservation $reservation, bool $invalidateSession = false): Reservation
    {
        $updates = [];

        if (!$reservation->guest_portal_token) {
            $updates['guest_portal_token'] = (string) Str::uuid();
        }

        if (!$reservation->guest_portal_enabled_at) {
            $updates['guest_portal_enabled_at'] = now();
        }

        if ($invalidateSession) {
            $updates['guest_portal_session_hash'] = null;
            $updates['guest_portal_session_expires_at'] = null;
            $updates['guest_portal_otp_hash'] = null;
            $updates['guest_portal_otp_expires_at'] = null;
            $updates['guest_portal_otp_attempts'] = 0;
        }

        if (!empty($updates)) {
            $reservation->update($updates);
            $reservation->refresh();
        }

        return $reservation;
    }

    public function buildPublicUrl(Reservation $reservation): string
    {
        $reservation = $this->ensureToken($reservation);
        $base = config('services.guest_portal.public_site_url');

        return $base . '/registro-huespedes/?token=' . $reservation->guest_portal_token;
    }

    public function findByToken(string $token): ?Reservation
    {
        if ($token === '') {
            return null;
        }

        return Reservation::query()
            ->where('guest_portal_token', $token)
            ->with(['customer', 'guests'])
            ->first();
    }

    public function assertAccessible(Reservation $reservation): void
    {
        if (in_array($reservation->status, self::BLOCKED_STATUSES, true)) {
            throw new \DomainException('Esta reserva ya no permite registro de huéspedes en línea.');
        }
    }

    public function getRecipient(Reservation $reservation): ?array
    {
        $reservation->loadMissing(['customer', 'guests']);

        if ($reservation->customer && $reservation->customer->email) {
            $name = $reservation->customer->customer_type === 'company'
                ? ($reservation->customer->company_name ?: 'Cliente')
                : trim(($reservation->customer->name ?? '') . ' ' . ($reservation->customer->last_name ?? ''));

            return [
                'email' => $reservation->customer->email,
                'name' => $name !== '' ? $name : 'Huésped',
            ];
        }

        $primaryGuest = $reservation->guests->firstWhere('is_primary_guest', true)
            ?? $reservation->guests->first();

        if ($primaryGuest && $primaryGuest->email) {
            return [
                'email' => $primaryGuest->email,
                'name' => trim($primaryGuest->first_name . ' ' . $primaryGuest->last_name) ?: 'Huésped',
            ];
        }

        return null;
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;
        $visible = mb_substr($local, 0, 1);
        return $visible . '***@' . $domain;
    }

    public function getGuestCapacity(Reservation $reservation): int
    {
        $total = (int) $reservation->adults + (int) $reservation->children + (int) $reservation->infants;

        if ($reservation->is_group_reservation) {
            $reservation->loadMissing('childReservations');
            foreach ($reservation->childReservations as $child) {
                $total += (int) $child->adults + (int) $child->children + (int) $child->infants;
            }
        }

        return max(1, $total);
    }

    public function formatPublicSummary(Reservation $reservation): array
    {
        $recipient = $this->getRecipient($reservation);
        $capacity = $this->getGuestCapacity($reservation);
        $registered = $reservation->guests()->count();

        return [
            'reservation_number' => $reservation->reservation_number,
            'reservation_type' => $reservation->reservation_type,
            'status' => $reservation->status,
            'check_in_date' => optional($reservation->check_in_date)->format('Y-m-d'),
            'check_out_date' => optional($reservation->check_out_date)->format('Y-m-d'),
            'capacity' => $capacity,
            'registered_guests' => $registered,
            'remaining_slots' => max(0, $capacity - $registered),
            'masked_email' => $recipient ? $this->maskEmail($recipient['email']) : null,
            'has_recipient_email' => (bool) $recipient,
            'portal_open' => !in_array($reservation->status, self::BLOCKED_STATUSES, true),
        ];
    }

    public function issueSession(Reservation $reservation): string
    {
        $plain = Str::random(64);
        $hours = (int) config('services.guest_portal.session_ttl_hours', 2);

        $reservation->update([
            'guest_portal_session_hash' => Hash::make($plain),
            'guest_portal_session_expires_at' => now()->addHours($hours),
            'guest_portal_otp_hash' => null,
            'guest_portal_otp_expires_at' => null,
            'guest_portal_otp_attempts' => 0,
        ]);

        return $plain;
    }

    public function validateSession(Reservation $reservation, ?string $sessionToken): bool
    {
        if (!$sessionToken || !$reservation->guest_portal_session_hash) {
            return false;
        }

        if (
            $reservation->guest_portal_session_expires_at
            && now()->gt($reservation->guest_portal_session_expires_at)
        ) {
            return false;
        }

        return Hash::check($sessionToken, $reservation->guest_portal_session_hash);
    }

    public function sendLinkEmail(Reservation $reservation): void
    {
        $reservation = $this->ensureToken($reservation, false);
        $recipient = $this->getRecipient($reservation);

        if (!$recipient) {
            throw new \DomainException('No hay email del titular para enviar el link del portal.');
        }

        $portalUrl = $this->buildPublicUrl($reservation);
        $logoUrl = $this->getLogoUrl();

        Mail::send(
            'emails.guest_portal_link',
            [
                'reservation' => $reservation,
                'recipient' => $recipient,
                'portalUrl' => $portalUrl,
                'logoUrl' => $logoUrl,
            ],
            function ($message) use ($recipient, $reservation) {
                $message->to($recipient['email'], $recipient['name'])
                    ->subject('Complete los datos de huéspedes - Reserva #' . $reservation->reservation_number);
            }
        );
    }

    public function invalidatePortalSession(Reservation $reservation): void
    {
        $reservation->update([
            'guest_portal_session_hash' => null,
            'guest_portal_session_expires_at' => null,
            'guest_portal_otp_hash' => null,
            'guest_portal_otp_expires_at' => null,
            'guest_portal_otp_attempts' => 0,
        ]);
    }

    public function staffLinkPayload(Reservation $reservation): array
    {
        $reservation = $this->ensureToken($reservation);
        $recipient = $this->getRecipient($reservation);

        return [
            'url' => $this->buildPublicUrl($reservation),
            'token' => $reservation->guest_portal_token,
            'enabled_at' => optional($reservation->guest_portal_enabled_at)?->toIso8601String(),
            'has_active_session' => $reservation->guest_portal_session_hash
                && $reservation->guest_portal_session_expires_at
                && now()->lte($reservation->guest_portal_session_expires_at),
            'masked_email' => $recipient ? $this->maskEmail($recipient['email']) : null,
            'has_recipient_email' => (bool) $recipient,
            'portal_open' => !in_array($reservation->status, self::BLOCKED_STATUSES, true),
        ];
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
