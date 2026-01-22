<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\MinibarInventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomMinibarInventoryController extends Controller
{
    protected $minibarService;

    public function __construct(MinibarInventoryService $minibarService)
    {
        $this->minibarService = $minibarService;
    }

    /**
     * Obtener inventario de una reserva
     */
    public function getByReservation(Reservation $reservation)
    {
        $inventory = $this->minibarService->getReservationInventory($reservation);

        return response($inventory, Response::HTTP_OK);
    }

    /**
     * Registrar inventario al check-in
     */
    public function recordCheckIn(Request $request, Reservation $reservation)
    {
        $validation = Validator::make($request->all(), [
            'products' => 'nullable|array',
            'products.*' => 'required|integer|min:0',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la reserva esté en estado correcto
        if ($reservation->status !== 'checked_in') {
            return response()->json([
                'message' => 'Solo se puede registrar inventario de minibar para reservas con check-in realizado'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $products = $request->products ?? [];
        $records = $this->minibarService->recordCheckInInventory(
            $reservation,
            $products,
            auth()->id()
        );

        return response([
            'message' => 'Inventario inicial registrado exitosamente',
            'records' => $records
        ], Response::HTTP_CREATED);
    }

    /**
     * Registrar inventario durante limpieza
     */
    public function recordCleaning(Request $request, Reservation $reservation)
    {
        $validation = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*' => 'required|integer|min:0',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la reserva esté en estado correcto
        if ($reservation->status !== 'checked_in') {
            return response()->json([
                'message' => 'Solo se puede registrar consumo durante limpieza para reservas con check-in realizado'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->minibarService->recordInventoryUpdate(
            $reservation,
            $request->products,
            'cleaning',
            auth()->id()
        );

        return response([
            'message' => 'Consumo registrado exitosamente',
            'records' => $result['records'],
            'charges' => $result['charges']
        ], Response::HTTP_OK);
    }

    /**
     * Registrar inventario al checkout
     */
    public function recordCheckOut(Request $request, Reservation $reservation)
    {
        $validation = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*' => 'required|integer|min:0',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la reserva esté en estado correcto
        if (!in_array($reservation->status, ['checked_in', 'checked_out'])) {
            return response()->json([
                'message' => 'Solo se puede registrar inventario final para reservas con check-in realizado'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->minibarService->recordInventoryUpdate(
            $reservation,
            $request->products,
            'check_out',
            auth()->id()
        );

        return response([
            'message' => 'Inventario final registrado exitosamente',
            'records' => $result['records'],
            'charges' => $result['charges'],
            'reservation_final_price' => $reservation->fresh()->final_price
        ], Response::HTTP_OK);
    }
}
