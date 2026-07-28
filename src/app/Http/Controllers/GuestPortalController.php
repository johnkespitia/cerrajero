<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Services\GuestPortalOtpService;
use App\Services\GuestPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GuestPortalController extends Controller
{
    public function __construct(
        protected GuestPortalService $portalService,
        protected GuestPortalOtpService $otpService
    ) {
    }

    public function show(string $token)
    {
        $reservation = $this->resolveReservation($token);
        if (!$reservation) {
            return response()->json(['message' => 'Link de registro no válido.'], 404);
        }

        // 200 aunque el portal esté cerrado: el frontend muestra estado "cerrado"
        // (un 422 se interpretaba como enlace inválido).
        return response()->json($this->portalService->formatPublicSummary($reservation));
    }

    public function requestOtp(string $token)
    {
        $reservation = $this->resolveReservation($token);
        if (!$reservation) {
            return response()->json(['message' => 'Link de registro no válido.'], 404);
        }

        try {
            $result = $this->otpService->requestOtp($reservation);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge([
            'message' => 'Código de verificación enviado.',
        ], $result));
    }

    public function verifyOtp(Request $request, string $token)
    {
        $validator = Validator::make($request->all(), [
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $reservation = $this->resolveReservation($token);
        if (!$reservation) {
            return response()->json(['message' => 'Link de registro no válido.'], 404);
        }

        try {
            $this->portalService->assertAccessible($reservation);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result = $this->otpService->verifyOtp($reservation, (string) $request->input('otp_code'));

        if (!$result['valid']) {
            return response()->json([
                'message' => $result['message'],
                'locked' => $result['locked'] ?? false,
            ], 422);
        }

        return response()->json([
            'message' => 'Código verificado correctamente.',
            'session_token' => $result['session_token'],
            'expires_in_hours' => $result['expires_in_hours'],
            'summary' => $this->portalService->formatPublicSummary($reservation->fresh()),
        ]);
    }

    public function guests(Request $request, string $token)
    {
        $auth = $this->authorizePortal($request, $token);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        /** @var Reservation $reservation */
        $reservation = $auth;

        return response()->json([
            'guests' => $reservation->guests()->orderByDesc('is_primary_guest')->orderBy('id')->get(),
            'capacity' => $this->portalService->getGuestCapacity($reservation),
            'summary' => $this->portalService->formatPublicSummary($reservation),
        ]);
    }

    public function storeGuest(Request $request, string $token)
    {
        $auth = $this->authorizePortal($request, $token);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        /** @var Reservation $reservation */
        $reservation = $auth;

        $validator = Validator::make($request->all(), $this->guestRules());
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        if (empty($data['document_type'])) {
            $data['document_type'] = 'CC';
        }

        try {
            $guest = DB::transaction(function () use ($reservation, $data) {
                /** @var Reservation $locked */
                $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                $capacity = $this->portalService->getGuestCapacity($locked);

                if ($locked->guests()->count() >= $capacity) {
                    throw new \DomainException(
                        "La reserva ya tiene registrados {$capacity} huésped(es) permitidos."
                    );
                }

                if (!empty($data['document_number'])) {
                    $exists = $locked->guests()
                        ->where('document_number', $data['document_number'])
                        ->where('document_type', $data['document_type'] ?? 'CC')
                        ->exists();

                    if ($exists) {
                        throw new \DomainException(
                            'Ya existe un huésped con ese documento en esta reserva.'
                        );
                    }
                }

                if (!empty($data['is_primary_guest'])) {
                    $locked->guests()->update(['is_primary_guest' => false]);
                    $data['is_primary_guest'] = true;
                } else {
                    $data['is_primary_guest'] = $locked->guests()->count() === 0;
                }

                $created = $locked->guests()->create($data);
                $this->normalizePrimaryGuest($locked);

                return $created;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Huésped registrado.',
            'guest' => $guest->fresh(),
            'summary' => $this->portalService->formatPublicSummary($reservation->fresh()),
        ], 201);
    }

    public function updateGuest(Request $request, string $token, ReservationGuest $guest)
    {
        $auth = $this->authorizePortal($request, $token);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        /** @var Reservation $reservation */
        $reservation = $auth;

        if ((int) $guest->reservation_id !== (int) $reservation->id) {
            return response()->json(['message' => 'Huésped no pertenece a esta reserva.'], 404);
        }

        $validator = Validator::make($request->all(), $this->guestRules());
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();

        if (!empty($data['document_number'])) {
            $exists = $reservation->guests()
                ->where('document_number', $data['document_number'])
                ->where('document_type', $data['document_type'] ?? $guest->document_type ?? 'CC')
                ->where('id', '!=', $guest->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un huésped con ese documento en esta reserva.',
                ], 422);
            }
        }

        if (array_key_exists('is_primary_guest', $data) && $data['is_primary_guest']) {
            $reservation->guests()->where('id', '!=', $guest->id)->update(['is_primary_guest' => false]);
            $data['is_primary_guest'] = true;
        }

        $guest->update($data);
        $this->normalizePrimaryGuest($reservation);

        return response()->json([
            'message' => 'Huésped actualizado.',
            'guest' => $guest->fresh(),
            'summary' => $this->portalService->formatPublicSummary($reservation->fresh()),
        ]);
    }

    public function destroyGuest(Request $request, string $token, ReservationGuest $guest)
    {
        $auth = $this->authorizePortal($request, $token);
        if ($auth instanceof \Illuminate\Http\JsonResponse) {
            return $auth;
        }

        /** @var Reservation $reservation */
        $reservation = $auth;

        if ((int) $guest->reservation_id !== (int) $reservation->id) {
            return response()->json(['message' => 'Huésped no pertenece a esta reserva.'], 404);
        }

        $guest->delete();
        $this->normalizePrimaryGuest($reservation);

        return response()->json([
            'message' => 'Huésped eliminado.',
            'summary' => $this->portalService->formatPublicSummary($reservation->fresh()),
        ]);
    }

    protected function guestRules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'document_type' => 'nullable|string|max:20',
            'document_number' => 'required|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:20',
            'special_needs' => 'nullable|string|max:500',
            'is_primary_guest' => 'nullable|boolean',
            'health_insurance_name' => 'nullable|string|max:200',
            'health_insurance_type' => 'nullable|in:national,international',
        ];
    }

    protected function resolveReservation(string $token): ?Reservation
    {
        return $this->portalService->findByToken($token);
    }

    /**
     * @return Reservation|\Illuminate\Http\JsonResponse
     */
    protected function authorizePortal(Request $request, string $token)
    {
        $reservation = $this->resolveReservation($token);
        if (!$reservation) {
            return response()->json(['message' => 'Link de registro no válido.'], 404);
        }

        try {
            $this->portalService->assertAccessible($reservation);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sessionToken = $this->extractSessionToken($request);
        if (!$this->portalService->validateSession($reservation, $sessionToken)) {
            return response()->json([
                'message' => 'Sesión inválida o expirada. Verifique el código OTP de nuevo.',
            ], 401);
        }

        return $reservation;
    }

    protected function extractSessionToken(Request $request): ?string
    {
        return $request->bearerToken() ?: $request->input('session_token');
    }

    protected function normalizePrimaryGuest(Reservation $reservation): void
    {
        $guests = $reservation->guests()->orderBy('id')->get();
        if ($guests->isEmpty()) {
            return;
        }

        $primary = $guests->firstWhere('is_primary_guest', true) ?? $guests->first();
        $reservation->guests()->update(['is_primary_guest' => false]);
        $primary->update(['is_primary_guest' => true]);
    }
}
