<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\RoomMinibarInventory;
use App\Services\MinibarInventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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

        // Validar que la reserva esté en estado correcto (permitir confirmed para registrar antes del check-in)
        if (!in_array($reservation->status, ['confirmed', 'checked_in'])) {
            return response()->json([
                'message' => 'Solo se puede registrar inventario de minibar para reservas confirmadas o con check-in realizado'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que no exista ya un registro de inventario de check-in para esta reserva
        $existingCheckIn = RoomMinibarInventory::where('reservation_id', $reservation->id)
            ->where('record_type', 'check_in')
            ->exists();

        if ($existingCheckIn) {
            return response()->json([
                'message' => 'Ya existe un registro de inventario de check-in para esta reserva. No se puede registrar otro.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $products = $request->products ?? [];
        try {
            $records = $this->minibarService->recordCheckInInventory(
                $reservation,
                $products,
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

        // No permitir más de una limpieza de minibar el mismo día por reserva/habitación
        $today = now()->toDateString();
        $alreadyCleanedToday = RoomMinibarInventory::where('reservation_id', $reservation->id)
            ->where('record_type', 'cleaning')
            ->whereDate('recorded_at', $today)
            ->exists();
        if ($alreadyCleanedToday) {
            return response()->json([
                'message' => 'Ya se registró una limpieza de minibar hoy para esta habitación. No se puede registrar otra el mismo día.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->minibarService->recordInventoryUpdate(
            $reservation,
            $request->products,
            'cleaning',
            auth()->id()
        );

        // Log para debugging
        Log::info('Minibar cleaning recorded', [
            'reservation_id' => $reservation->id,
            'records_count' => count($result['records']),
            'charges_count' => count($result['charges']),
            'charges' => $result['charges']
        ]);

        return response([
            'message' => 'Consumo registrado exitosamente',
            'records' => $result['records'],
            'charges' => $result['charges'],
            'charges_total' => array_sum(array_column($result['charges'], 'total'))
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
