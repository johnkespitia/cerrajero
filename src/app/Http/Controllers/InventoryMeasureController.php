<?php

namespace App\Http\Controllers;

use App\Models\InventoryMeasure;
use App\Models\InventoryMeasureConversion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InventoryMeasureController extends Controller
{
    public function index()
    {
        $measures = InventoryMeasure::with('conversionsOrigin.destinationMeasure')->get();
        return response($measures, Response::HTTP_OK);
    }

    public function conversions()
    {
        $measures = InventoryMeasureConversion::with('originMeasure')->with('destinationMeasure')->get();
        return response($measures, Response::HTTP_OK);
    }

    public function getConversions($measureId)
    {
        $measure = InventoryMeasure::findOrFail($measureId);
        $conversions = $measure->conversionsOrigin;

        return response()->json($conversions);
    }


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|unique:inventory_measures,name'
        ]);

        $measure = InventoryMeasure::create([
            'name' => $validatedData['name']
        ]);

        return response()->json([
            'message' => 'Medida creada exitosamente',
            'measure' => $measure
        ], 201);
    }
    public function storeConversion(Request $request)
    {
        $validatedData = $request->validate([
            'origin_id' => 'required|exists:inventory_measures,id',
            'destination_id' => 'required|exists:inventory_measures,id',
            'factor' => 'required|numeric'
        ]);

        // Crear la nueva conversión
        $conversion = InventoryMeasureConversion::create([
            'origin_id' => $validatedData['origin_id'],
            'destination_id' => $validatedData['destination_id'],
            'factor' => $validatedData['factor']
        ]);

        return response()->json([
            'message' => 'Conversión creada exitosamente',
            'conversion' => $conversion
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\inventoryCategory  $inventoryCategory
     * @return \Illuminate\Http\Response
     */
    public function show(InventoryMeasure $inventoryMeasure)
    {
        $inventoryMeasure->conversions_origin;
        return response($inventoryMeasure, Response::HTTP_OK);
    }


    public function update(Request $request, InventoryMeasure $inventoryMeasure)
    {
        $validatedData = $request->validate([
            'name' => 'required|unique:inventory_measures,name,' . $inventoryMeasure->id
        ]);

        // Actualizar la medida
        $inventoryMeasure->update([
            'name' => $validatedData['name']
        ]);

        return response()->json([
            'message' => 'Medida actualizada exitosamente',
            'measure' => $inventoryMeasure
        ], 200);
    }

    public function updateConversion(Request $request, InventoryMeasureConversion $conversion)
    {
        // Validar los datos de la conversión actualizada
        $validatedData = $request->validate([
            'origin_id' => 'sometimes|exists:inventory_measures,id',
            'destination_id' => 'sometimes|exists:inventory_measures,id',
            'factor' => 'sometimes|numeric'
        ]);

        $conversion->update([
            'origin_id' => $validatedData['origin_id']??$conversion->origin_id,
            'destination_id' => $validatedData['destination_id']??$conversion->destination_id,
            'factor' => $validatedData['factor']??$conversion->factor
        ]);

        return response()->json([
            'message' => 'Conversión actualizada exitosamente',
            'conversion' => $conversion
        ], 200);
    }

    public function convert(Request $request)
    {
        $quantity = $request->input('quantity');
        $originMeasureId = $request->input('origin_measure_id');
        $destinationMeasureId = $request->input('destination_measure_id');

        $conversion = InventoryMeasureConversion::where('origin_id', $originMeasureId)
            ->where('destination_id', $destinationMeasureId)
            ->orWhere(function ($query) use ($originMeasureId, $destinationMeasureId) {
                $query->where('destination_id', $originMeasureId)
                    ->where('origin_id', $destinationMeasureId);
            })
            ->first();

        if (!$conversion) {
            return response()->json([
                'error' => 'Conversion not found'
            ], 404);
        }

        if ($conversion->origin_id === $originMeasureId) {
            $convertedQuantity = $quantity * $conversion->factor;
        } else {
            $convertedQuantity = $quantity / $conversion->factor;
        }

        return response()->json([
            'quantity' => $quantity,
            'origin_measure' => $conversion->originMeasure->name,
            'destination_measure' => $conversion->destinationMeasure->name,
            'converted_quantity' => $convertedQuantity
        ]);
    }
}
