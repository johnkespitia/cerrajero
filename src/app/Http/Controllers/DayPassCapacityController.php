<?php

namespace App\Http\Controllers;

use App\Models\DayPassCapacity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|unique:day_pass_capacities,date',
            'max_capacity' => 'required|integer|min:0',
            'consumed_capacity' => 'integer|min:0',
            'adult_price' => 'required|numeric|min:0',
            'child_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $capacity = DayPassCapacity::create($request->all());

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

