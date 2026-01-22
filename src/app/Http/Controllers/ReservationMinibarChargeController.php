<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationMinibarCharge;
use App\Services\MinibarInventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ReservationMinibarChargeController extends Controller
{
    protected $minibarService;

    public function __construct(MinibarInventoryService $minibarService)
    {
        $this->minibarService = $minibarService;
    }

    /**
     * Obtener cargos del minibar de una reserva
     */
    public function getByReservation(Reservation $reservation)
    {
        $charges = $this->minibarService->getReservationCharges($reservation);

        return response([
            'charges' => $charges,
            'total' => $reservation->minibar_charges_total
        ], Response::HTTP_OK);
    }

    /**
     * Eliminar cargo
     */
    public function destroy(ReservationMinibarCharge $charge)
    {
        // Validar que la reserva no esté pagada o en checkout
        $reservation = $charge->reservation;
        
        if ($reservation->status === 'checked_out' && $reservation->total_paid > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el cargo porque la reserva ya está pagada'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $charge->delete();

        // Recalcular precio final
        $reservation->recomputeFinalPrice();

        return response()->json([
            'message' => 'Cargo eliminado exitosamente',
            'reservation_final_price' => $reservation->fresh()->final_price
        ], Response::HTTP_OK);
    }
}
