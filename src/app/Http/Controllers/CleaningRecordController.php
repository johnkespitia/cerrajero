<?php

namespace App\Http\Controllers;

use App\Models\CleaningRecord;
use App\Models\Room;
use App\Models\CommonArea;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CleaningRecordController extends Controller
{
    /**
     * Listar registros de aseo
     */
    public function index(Request $request)
    {
        $query = CleaningRecord::with(['cleanable', 'cleanedBy', 'supervisor', 'reservation']);

        // Filtros
        if ($request->has('room_id')) {
            $query->where('cleanable_type', Room::class)
                  ->where('cleanable_id', $request->room_id);
        }

        if ($request->has('common_area_id')) {
            $query->where('cleanable_type', CommonArea::class)
                  ->where('cleanable_id', $request->common_area_id);
        }

        if ($request->has('reservation_id')) {
            $query->where('reservation_id', $request->reservation_id);
        }

        if ($request->has('cleaned_by')) {
            $query->where('cleaned_by', $request->cleaned_by);
        }

        if ($request->has('cleaning_type')) {
            $query->where('cleaning_type', $request->cleaning_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('cleaning_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('cleaning_date', '<=', $request->date_to);
        }

        $records = $query->orderBy('cleaning_date', 'desc')
                        ->orderBy('cleaning_time', 'desc')
                        ->get();

        return response($records, Response::HTTP_OK);
    }

    /**
     * Crear registro de aseo
     */
    public function store(Request $request)
    {
        // Normalizar el cleanable_type
        $cleanableType = str_replace('\\\\', '\\', $request->cleanable_type ?? '');
        
        $validation = Validator::make([
            'cleanable_type' => $cleanableType,
            'cleanable_id' => $request->cleanable_id,
            'reservation_id' => $request->reservation_id,
            'cleaned_by' => $request->cleaned_by,
            'cleaning_date' => $request->cleaning_date,
            'cleaning_time' => $request->cleaning_time,
            'cleaning_type' => $request->cleaning_type,
            'status' => $request->status,
            'duration_minutes' => $request->duration_minutes,
            'observations' => $request->observations,
            'issues_found' => $request->issues_found,
            'items_missing' => $request->items_missing,
            'quality_score' => $request->quality_score,
        ], [
            'cleanable_type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, [Room::class, CommonArea::class])) {
                    $fail('The selected cleanable type is invalid.');
                }
            }],
            'cleanable_id' => 'required|integer',
            'reservation_id' => 'nullable|exists:reservations,id',
            'cleaned_by' => 'nullable|exists:users,id',
            'cleaning_date' => 'required|date',
            'cleaning_time' => 'nullable|date_format:H:i',
            'cleaning_type' => 'required|in:daily,checkout,checkin,deep,maintenance',
            'status' => 'nullable|in:completed,pending',
            'duration_minutes' => 'nullable|integer|min:1',
            'observations' => 'nullable|string',
            'issues_found' => 'nullable|string',
            'items_missing' => 'nullable|string',
            'quality_score' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la ubicación existe
        $cleanable = $cleanableType::find($request->cleanable_id);
        if (!$cleanable) {
            return response(['message' => 'La ubicación especificada no existe'], Response::HTTP_NOT_FOUND);
        }

        // Validar que si es checkin/checkout, sea una habitación
        if (in_array($request->cleaning_type, ['checkin', 'checkout']) && $cleanableType !== Room::class) {
            return response(['message' => 'Las limpiezas de check-in y check-out solo aplican para habitaciones'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Si hay reservation_id, validar que la reserva tenga room_id asignado
        if ($request->reservation_id) {
            $reservation = Reservation::find($request->reservation_id);
            if (!$reservation || !$reservation->room_id) {
                return response(['message' => 'La reserva no tiene habitación asignada'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $record = CleaningRecord::create([
            'cleanable_type' => $cleanableType,
            'cleanable_id' => $request->cleanable_id,
            'reservation_id' => $request->reservation_id,
            'cleaned_by' => $request->cleaned_by,
            'cleaning_date' => $request->cleaning_date,
            'cleaning_time' => $request->cleaning_time,
            'cleaning_type' => $request->cleaning_type,
            'status' => $request->status ?? 'completed',
            'duration_minutes' => $request->duration_minutes,
            'observations' => $request->observations,
            'issues_found' => $request->issues_found,
            'items_missing' => $request->items_missing,
            'quality_score' => $request->quality_score,
        ]);

        $record->load(['cleanable', 'cleanedBy', 'supervisor', 'reservation']);

        return response([
            'message' => 'Registro de aseo creado exitosamente',
            'record' => $record
        ], Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de registro
     */
    public function show(CleaningRecord $cleaningRecord)
    {
        $cleaningRecord->load(['cleanable', 'cleanedBy', 'supervisor', 'reservation']);
        return response($cleaningRecord, Response::HTTP_OK);
    }

    /**
     * Actualizar registro
     */
    public function update(Request $request, CleaningRecord $cleaningRecord)
    {
        $validation = Validator::make($request->all(), [
            'cleaned_by' => 'nullable|exists:users,id',
            'cleaning_date' => 'sometimes|date',
            'cleaning_time' => 'nullable|date_format:H:i',
            'cleaning_type' => 'sometimes|in:daily,checkout,checkin,deep,maintenance',
            'status' => 'sometimes|in:completed,in_progress,pending',
            'duration_minutes' => 'nullable|integer|min:1',
            'observations' => 'nullable|string',
            'issues_found' => 'nullable|string',
            'items_missing' => 'nullable|string',
            'quality_score' => 'nullable|integer|min:1|max:10',
            'supervisor_checked' => 'nullable|boolean',
            'supervisor_id' => 'nullable|exists:users,id',
            'supervisor_notes' => 'nullable|string',
            'next_cleaning_due' => 'nullable|date',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cleaningRecord->update($request->all());
        $cleaningRecord->load(['cleanable', 'cleanedBy', 'supervisor', 'reservation']);

        return response([
            'message' => 'Registro de aseo actualizado exitosamente',
            'record' => $cleaningRecord
        ], Response::HTTP_OK);
    }

    /**
     * Obtener historial de aseo de una habitación
     */
    public function getByRoom($roomId)
    {
        $records = CleaningRecord::where('cleanable_type', Room::class)
            ->where('cleanable_id', $roomId)
            ->with(['cleanedBy', 'supervisor', 'reservation'])
            ->orderBy('cleaning_date', 'desc')
            ->orderBy('cleaning_time', 'desc')
            ->get();

        return response($records, Response::HTTP_OK);
    }

    /**
     * Obtener historial de aseo de una zona común
     */
    public function getByCommonArea($areaId)
    {
        $records = CleaningRecord::where('cleanable_type', CommonArea::class)
            ->where('cleanable_id', $areaId)
            ->with(['cleanedBy', 'supervisor'])
            ->orderBy('cleaning_date', 'desc')
            ->orderBy('cleaning_time', 'desc')
            ->get();

        return response($records, Response::HTTP_OK);
    }

    /**
     * Obtener limpiezas de una reserva
     */
    public function getByReservation($reservationId)
    {
        $records = CleaningRecord::where('reservation_id', $reservationId)
            ->with(['cleanable', 'cleanedBy', 'supervisor'])
            ->orderBy('cleaning_date', 'asc')
            ->get();

        return response($records, Response::HTTP_OK);
    }

    /**
     * Obtener registros de un empleado
     */
    public function getByEmployee($userId)
    {
        $records = CleaningRecord::where('cleaned_by', $userId)
            ->with(['cleanable', 'supervisor', 'reservation'])
            ->orderBy('cleaning_date', 'desc')
            ->get();

        return response($records, Response::HTTP_OK);
    }

    /**
     * Obtener limpiezas pendientes
     */
    public function getPending(Request $request)
    {
        $query = CleaningRecord::where('status', 'pending')
            ->with(['cleanable', 'reservation']);

        if ($request->has('date')) {
            $query->where('cleaning_date', $request->date);
        } else {
            // Por defecto, mostrar las del día actual y futuras
            $query->where('cleaning_date', '>=', Carbon::today());
        }

        if ($request->has('cleaning_type')) {
            $query->where('cleaning_type', $request->cleaning_type);
        }

        $records = $query->orderBy('cleaning_date', 'asc')
                        ->orderBy('cleaning_time', 'asc')
                        ->get();

        return response($records, Response::HTTP_OK);
    }

    /**
     * Completar limpieza pendiente
     */
    public function completePending(Request $request, CleaningRecord $cleaningRecord)
    {
        if ($cleaningRecord->status !== 'pending') {
            return response([
                'message' => 'Solo se pueden completar limpiezas pendientes'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validation = Validator::make($request->all(), [
            'cleaned_by' => 'required|exists:users,id',
            'cleaning_time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'observations' => 'nullable|string',
            'issues_found' => 'nullable|string',
            'items_missing' => 'nullable|string',
            'quality_score' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cleaningRecord->update([
            'cleaned_by' => $request->cleaned_by,
            'cleaning_time' => $request->cleaning_time ?? now()->format('H:i'),
            'status' => 'completed',
            'duration_minutes' => $request->duration_minutes,
            'observations' => $request->observations,
            'issues_found' => $request->issues_found,
            'items_missing' => $request->items_missing,
            'quality_score' => $request->quality_score,
        ]);

        $cleaningRecord->load(['cleanable', 'cleanedBy', 'supervisor', 'reservation']);

        return response([
            'message' => 'Limpieza completada exitosamente',
            'record' => $cleaningRecord
        ], Response::HTTP_OK);
    }

    /**
     * Estadísticas de aseo
     */
    public function getStatistics(Request $request)
    {
        $query = CleaningRecord::query();

        // Filtros
        if ($request->has('date_from')) {
            $query->where('cleaning_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('cleaning_date', '<=', $request->date_to);
        }

        if ($request->has('cleaning_type')) {
            $query->where('cleaning_type', $request->cleaning_type);
        }

        $stats = [
            'total_cleanings' => $query->count(),
            'total_completed' => (clone $query)->where('status', 'completed')->count(),
            'total_pending' => (clone $query)->where('status', 'pending')->count(),
            'average_quality_score' => (clone $query)->whereNotNull('quality_score')->avg('quality_score'),
            'average_duration' => (clone $query)->whereNotNull('duration_minutes')->avg('duration_minutes'),
        ];

        return response($stats, Response::HTTP_OK);
    }
}
