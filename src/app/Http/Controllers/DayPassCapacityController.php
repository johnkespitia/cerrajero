<?php

namespace App\Http\Controllers;

use App\Models\DayPassCapacity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DayPassCapacityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DayPassCapacity::query();

        // Filtrar por rango de fechas si se proporciona
        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        // Ordenar por fecha descendente
        $query->orderBy('date', 'desc');

        // Formatear fechas como Y-m-d para evitar problemas de timezone
        $capacities = $query->get()->map(function ($capacity) {
            $capacity->date = $capacity->date ? $capacity->date->format('Y-m-d') : null;
            return $capacity;
        });

        return response()->json($capacities);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Normalizar la fecha a formato Y-m-d (solo fecha, sin hora ni timezone)
        // Esto evita problemas de timezone al comparar fechas
        $normalizedDate = null;
        if ($request->has('date') && $request->date) {
            try {
                // Si ya viene en formato Y-m-d, usarlo directamente
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->date)) {
                    $normalizedDate = $request->date;
                } else {
                    // Parsear la fecha y normalizarla a Y-m-d (solo fecha, sin hora)
                    $normalizedDate = Carbon::parse($request->date)->startOfDay()->format('Y-m-d');
                }
            } catch (\Exception $e) {
                \Log::error('Error parsing date in DayPassCapacity store: ' . $e->getMessage(), [
                    'request_date' => $request->date,
                    'exception' => $e
                ]);
                return response()->json([
                    'date' => ['La fecha proporcionada no es válida.']
                ], 422);
            }
        }

        if (!$normalizedDate) {
            return response()->json([
                'date' => ['La fecha es requerida.']
            ], 422);
        }

        // Verificar manualmente si la fecha ya existe usando múltiples métodos
        // 1. whereDate (ignora hora y timezone) - método más robusto
        $existingCapacity = DayPassCapacity::whereDate('date', $normalizedDate)->first();
        
        // 2. Si no encuentra, verificar con formato exacto (por si acaso)
        if (!$existingCapacity) {
            $existingCapacity = DayPassCapacity::whereRaw('DATE(date) = ?', [$normalizedDate])->first();
        }
        
        if ($existingCapacity) {
            // Log para debug
            \Log::warning('Attempted to create duplicate DayPassCapacity', [
                'requested_date' => $normalizedDate,
                'request_raw_date' => $request->date,
                'existing_id' => $existingCapacity->id,
                'existing_date' => $existingCapacity->date,
                'existing_date_formatted' => $existingCapacity->date ? $existingCapacity->date->format('Y-m-d') : null
            ]);
            
            return response()->json([
                'date' => ['La fecha ' . $normalizedDate . ' ya ha sido registrada.']
            ], 422);
        }

        // Validar otros campos (sin validar unique en date, ya lo hicimos manualmente)
        $validator = Validator::make([
            'date' => $normalizedDate,
            'max_capacity' => $request->max_capacity,
            'consumed_capacity' => $request->consumed_capacity,
            'adult_price' => $request->adult_price,
            'child_price' => $request->child_price,
            'notes' => $request->notes,
        ], [
            'date' => 'required|date',
            'max_capacity' => 'required|integer|min:0',
            'consumed_capacity' => 'integer|min:0',
            'adult_price' => 'required|numeric|min:0',
            'child_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Crear con la fecha normalizada
        $data = $request->all();
        $data['date'] = $normalizedDate;
        $capacity = DayPassCapacity::create($data);

        // Formatear la fecha en la respuesta para evitar problemas de timezone
        $capacity->date = $capacity->date ? $capacity->date->format('Y-m-d') : null;

        return response()->json($capacity, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DayPassCapacity $dayPassCapacity)
    {
        return response()->json($dayPassCapacity);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DayPassCapacity $dayPassCapacity)
    {
        $validator = Validator::make($request->all(), [
            'date' => [
                'sometimes',
                'date',
                Rule::unique('day_pass_capacities', 'date')->ignore($dayPassCapacity->id)
            ],
            'max_capacity' => 'sometimes|integer|min:0',
            'consumed_capacity' => 'sometimes|integer|min:0',
            'adult_price' => 'sometimes|numeric|min:0',
            'child_price' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Filtrar solo los campos que están en el fillable
        // No incluir la fecha en la actualización (no se puede cambiar al editar)
        $data = $request->only([
            'max_capacity',
            'consumed_capacity',
            'adult_price',
            'child_price',
            'notes'
        ]);

        $dayPassCapacity->update($data);
        
        // Recargar para obtener datos actualizados
        $dayPassCapacity->refresh();
        
        // Formatear la fecha en la respuesta para evitar problemas de timezone
        $dayPassCapacity->date = $dayPassCapacity->date ? $dayPassCapacity->date->format('Y-m-d') : null;

        return response()->json($dayPassCapacity);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DayPassCapacity $dayPassCapacity)
    {
        $dayPassCapacity->delete();

        return response()->json(null, 204);
    }

    /**
     * Verificar disponibilidad para una fecha y número de personas
     */
    public function checkAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'people' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $people = $request->people;

        $capacity = DayPassCapacity::getOrCreateForDate($date, 0, 0, 0);

        $available = $capacity->hasCapacityFor($people);

        return response()->json([
            'date' => $date,
            'max_capacity' => $capacity->max_capacity,
            'consumed_capacity' => $capacity->consumed_capacity,
            'available_capacity' => $capacity->available_capacity,
            'requested_people' => $people,
            'available' => $available,
            'adult_price' => $capacity->adult_price,
            'child_price' => $capacity->child_price,
        ]);
    }
}

