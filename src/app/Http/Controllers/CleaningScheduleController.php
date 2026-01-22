<?php

namespace App\Http\Controllers;

use App\Models\CleaningSchedule;
use App\Models\Room;
use App\Models\CommonArea;
use App\Models\CleaningRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CleaningScheduleController extends Controller
{
    /**
     * Listar programaciones de aseo
     */
    public function index(Request $request)
    {
        $query = CleaningSchedule::with(['cleanable', 'assignedTo']);

        // Filtros
        if ($request->has('room_id')) {
            $query->where('cleanable_type', Room::class)
                  ->where('cleanable_id', $request->room_id);
        }

        if ($request->has('common_area_id')) {
            $query->where('cleanable_type', CommonArea::class)
                  ->where('cleanable_id', $request->common_area_id);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('cleaning_type')) {
            $query->where('cleaning_type', $request->cleaning_type);
        }

        $schedules = $query->orderBy('next_cleaning_date', 'asc')->get();

        return response($schedules, Response::HTTP_OK);
    }

    /**
     * Crear programación de aseo
     */
    public function store(Request $request)
    {
        // Normalizar el cleanable_type
        $cleanableType = str_replace('\\\\', '\\', $request->cleanable_type ?? '');
        
        $validation = Validator::make([
            'cleanable_type' => $cleanableType,
            'cleanable_id' => $request->cleanable_id,
            'cleaning_type' => $request->cleaning_type,
            'frequency_days' => $request->frequency_days,
            'assigned_to' => $request->assigned_to,
            'time_preference' => $request->time_preference,
            'day_of_week' => $request->day_of_week,
            'active' => $request->active,
            'notes' => $request->notes,
        ], [
            'cleanable_type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, [Room::class, CommonArea::class])) {
                    $fail('The selected cleanable type is invalid.');
                }
            }],
            'cleanable_id' => 'required|integer',
            'cleaning_type' => 'required|in:daily,checkout,deep,maintenance',
            'frequency_days' => 'required|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'time_preference' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:1|max:7',
            'active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la ubicación existe
        $cleanable = $cleanableType::find($request->cleanable_id);
        if (!$cleanable) {
            return response(['message' => 'La ubicación especificada no existe'], Response::HTTP_NOT_FOUND);
        }

        // Verificar que no exista ya una programación activa para esta ubicación y tipo
        $existingSchedule = CleaningSchedule::where('cleanable_type', $cleanableType)
            ->where('cleanable_id', $request->cleanable_id)
            ->where('cleaning_type', $request->cleaning_type)
            ->where('active', true)
            ->first();

        if ($existingSchedule) {
            return response([
                'message' => 'Ya existe una programación activa para esta ubicación y tipo de limpieza'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Calcular próxima fecha de limpieza
        $lastCleanedDate = $request->last_cleaned_date ?? null;
        $nextCleaningDate = null;
        if ($lastCleanedDate) {
            $nextCleaningDate = Carbon::parse($lastCleanedDate)->addDays($request->frequency_days)->toDateString();
        } else {
            $nextCleaningDate = Carbon::today()->addDays($request->frequency_days)->toDateString();
        }

        $schedule = CleaningSchedule::create([
            'cleanable_type' => $cleanableType,
            'cleanable_id' => $request->cleanable_id,
            'cleaning_type' => $request->cleaning_type,
            'frequency_days' => $request->frequency_days,
            'assigned_to' => $request->assigned_to,
            'time_preference' => $request->time_preference,
            'day_of_week' => $request->day_of_week,
            'active' => $request->active ?? true,
            'last_cleaned_date' => $lastCleanedDate,
            'next_cleaning_date' => $nextCleaningDate,
            'notes' => $request->notes,
        ]);

        $schedule->load(['cleanable', 'assignedTo']);

        return response([
            'message' => 'Programación de aseo creada exitosamente',
            'schedule' => $schedule
        ], Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de programación
     */
    public function show(CleaningSchedule $cleaningSchedule)
    {
        $cleaningSchedule->load(['cleanable', 'assignedTo']);
        return response($cleaningSchedule, Response::HTTP_OK);
    }

    /**
     * Actualizar programación
     */
    public function update(Request $request, CleaningSchedule $cleaningSchedule)
    {
        $validation = Validator::make($request->all(), [
            'cleaning_type' => 'sometimes|in:daily,checkout,deep,maintenance',
            'frequency_days' => 'sometimes|integer|min:1',
            'assigned_to' => 'nullable|exists:users,id',
            'time_preference' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:1|max:7',
            'active' => 'nullable|boolean',
            'last_cleaned_date' => 'nullable|date',
            'next_cleaning_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Si cambia la frecuencia o la última fecha de limpieza, recalcular próxima fecha
        if ($request->has('frequency_days') || $request->has('last_cleaned_date')) {
            $lastCleanedDate = $request->last_cleaned_date ?? $cleaningSchedule->last_cleaned_date;
            $frequencyDays = $request->frequency_days ?? $cleaningSchedule->frequency_days;
            
            if ($lastCleanedDate) {
                $request->merge([
                    'next_cleaning_date' => Carbon::parse($lastCleanedDate)->addDays($frequencyDays)->toDateString()
                ]);
            } else {
                $request->merge([
                    'next_cleaning_date' => Carbon::today()->addDays($frequencyDays)->toDateString()
                ]);
            }
        }

        $cleaningSchedule->update($request->all());
        $cleaningSchedule->load(['cleanable', 'assignedTo']);

        return response([
            'message' => 'Programación de aseo actualizada exitosamente',
            'schedule' => $cleaningSchedule
        ], Response::HTTP_OK);
    }

    /**
     * Obtener programación de una habitación
     */
    public function getByRoom($roomId)
    {
        $schedule = CleaningSchedule::where('cleanable_type', Room::class)
            ->where('cleanable_id', $roomId)
            ->with(['assignedTo'])
            ->first();

        return response($schedule, Response::HTTP_OK);
    }

    /**
     * Obtener programación de una zona común
     */
    public function getByCommonArea($areaId)
    {
        $schedule = CleaningSchedule::where('cleanable_type', CommonArea::class)
            ->where('cleanable_id', $areaId)
            ->with(['assignedTo'])
            ->first();

        return response($schedule, Response::HTTP_OK);
    }

    /**
     * Obtener limpiezas programadas para hoy y próximos días
     */
    public function getDueCleanings(Request $request)
    {
        $days = $request->input('days', 7); // Por defecto, próximos 7 días
        $date = $request->input('date', Carbon::today()->toDateString());

        $query = CleaningSchedule::where('active', true)
            ->where('next_cleaning_date', '>=', $date)
            ->where('next_cleaning_date', '<=', Carbon::parse($date)->addDays($days)->toDateString())
            ->with(['cleanable', 'assignedTo']);

        if ($request->has('cleaning_type')) {
            $query->where('cleaning_type', $request->cleaning_type);
        }

        $schedules = $query->orderBy('next_cleaning_date', 'asc')
                          ->orderBy('time_preference', 'asc')
                          ->get();

        return response($schedules, Response::HTTP_OK);
    }

    /**
     * Marcar limpieza como realizada y calcular próxima fecha
     */
    public function markAsCleaned(Request $request, CleaningSchedule $cleaningSchedule)
    {
        $validation = Validator::make($request->all(), [
            'cleaning_date' => 'required|date',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cleaningDate = Carbon::parse($request->cleaning_date);

        // Actualizar última fecha de limpieza y calcular próxima
        $cleaningSchedule->update([
            'last_cleaned_date' => $cleaningDate->toDateString(),
            'next_cleaning_date' => $cleaningDate->copy()->addDays($cleaningSchedule->frequency_days)->toDateString(),
        ]);

        // Opcionalmente crear un registro de limpieza
        if ($request->boolean('create_record', false)) {
            CleaningRecord::create([
                'cleanable_type' => $cleaningSchedule->cleanable_type,
                'cleanable_id' => $cleaningSchedule->cleanable_id,
                'cleaned_by' => $request->cleaned_by ?? $cleaningSchedule->assigned_to,
                'cleaning_date' => $cleaningDate->toDateString(),
                'cleaning_time' => $cleaningSchedule->time_preference,
                'cleaning_type' => $cleaningSchedule->cleaning_type,
                'status' => 'completed',
            ]);
        }

        $cleaningSchedule->load(['cleanable', 'assignedTo']);

        return response([
            'message' => 'Limpieza marcada como realizada. Próxima fecha calculada.',
            'schedule' => $cleaningSchedule
        ], Response::HTTP_OK);
    }
}
