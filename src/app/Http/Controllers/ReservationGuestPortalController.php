<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\GuestPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservationGuestPortalController extends Controller
{
    public function __construct(protected GuestPortalService $portalService)
    {
    }

    public function show(Reservation $reservation)
    {
        return response()->json($this->portalService->staffLinkPayload($reservation));
    }

    public function store(Request $request, Reservation $reservation)
    {
        $validator = Validator::make($request->all(), [
            'send_email' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $this->portalService->assertAccessible($reservation);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sendEmail = $request->boolean('send_email');

        // Asegura token sin invalidar aún (si el email falla, no tumbar sesión vigente).
        $this->portalService->ensureToken($reservation, false);

        if ($sendEmail) {
            try {
                $this->portalService->sendLinkEmail($reservation->fresh());
                // Tras envío exitoso: invalidar sesión previa (link regenerado/compartido de nuevo).
                $this->portalService->invalidatePortalSession($reservation->fresh());
            } catch (\DomainException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'No se pudo enviar el email con el link del portal.',
                ], 500);
            }
        } else {
            // Copiar link también invalida sesión activa (mismo contrato de seguridad).
            $this->portalService->invalidatePortalSession($reservation->fresh());
        }

        return response()->json(array_merge(
            $this->portalService->staffLinkPayload($reservation->fresh()),
            [
                'message' => $sendEmail
                    ? 'Link del portal enviado por email.'
                    : 'Link del portal listo para copiar.',
            ]
        ));
    }
}
