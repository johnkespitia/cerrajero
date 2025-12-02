<?php

namespace App\Http\Controllers;

use App\Models\KioskUnit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;


class KioskUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskUnit::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            $request->validate([
                'code_complement' => 'required',
                'price' => 'required|numeric|min:0',
                'expiration' => 'date',
                'active' => 'boolean',
                'product_id' => 'required|exists:kiosk_products,id',
                'quantity' => 'required|numeric'
            ]);
            $units = [];
            for ($i=0; $i < $request->get("quantity"); $i++) {
                $values = $request->toArray();
                $units[]=[
                    'code_complement' => $values["code_complement"]."-{$i}",
                    'price' => $values["price"],
                    'expiration' => $values["expiration"],
                    'active' => $values["active"],
                    'product_id' => $values["product_id"],
                ];
            }
            $kioskUnit = KioskUnit::insert($units);
            return response()->json($kioskUnit, 201);
        }catch(ValidationException $ve){
            return response()->json([
                'errors' => $ve->errors()
            ], 422);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(KioskUnit $kioskUnit)
    {
        return $kioskUnit;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskUnit $kioskUnit)
    {
        $request->validate([
            'code_complement' => 'sometimes',
            'price' => 'numeric|min:0',
            'expiration' => 'date',
            'active' => 'boolean',
            'product_id' => 'exists:kiosk_products,id',
            'quantity' => 'numeric:min:0',
        ]);
        $existsUnits = KioskUnit::where("expiration","=", $kioskUnit->expiration)->where("price","=",$kioskUnit->price)->where("active","=",$kioskUnit->active)->where("sold","=",$kioskUnit->sold)->get();
        $totalQty = 0;
        if($request->get("quantity") > 0){
            $totalQty=$request->get("quantity") - sizeof($existsUnits);
        }
        if($totalQty < 0){
            $chunk = $existsUnits->splice($request->get("quantity"));
            $toDeleteIds = $chunk->reduce(function($ids, $unitInternal){
                $ids[] = $unitInternal->id;
                return $ids;
            },[]);
            KioskUnit::destroy($toDeleteIds);
        }elseif($totalQty > 0){
            $units = [];
            for ($i=0; $i < $totalQty; $i++) {
                $values = $request->toArray();
                $units[]=[
                    'code_complement' => ($values["code_complement"]??$kioskUnit->code_complement)."-{$i}",
                    'price' => $values["price"]??$kioskUnit->price,
                    'expiration' => $values["expiration"]??$kioskUnit->expiration,
                    'active' => $values["active"]??$kioskUnit->active,
                    'product_id' => $values["product_id"]??$kioskUnit->product_id,
                ];
            }
            $kioskUnit = KioskUnit::insert($units);
        }
        foreach($existsUnits as $unit){
            $unit->update($request->all());
        }
        return response()->json($kioskUnit, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskUnit $kioskUnit)
    {
        $kioskUnit->delete();
        return response()->json(null, 204);
    }

    /**
     * Actualizar múltiples unidades en bloque
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'required|exists:kiosk_units,id',
            'fields' => 'required|array',
            'fields.active' => 'sometimes|boolean',
            'fields.expiration' => 'sometimes|date',
            'fields.price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $unitIds = $request->unit_ids;
        $fields = $request->fields;

        // Validar que las unidades no estén vendidas
        $soldUnits = KioskUnit::whereIn('id', $unitIds)
            ->where('sold', true)
            ->pluck('id');

        if ($soldUnits->count() > 0) {
            return response()->json([
                'message' => 'No se pueden editar unidades vendidas',
                'sold_units' => $soldUnits
            ], 422);
        }

        $updated = KioskUnit::whereIn('id', $unitIds)
            ->update($fields);

        return response()->json([
            'message' => 'Unidades actualizadas exitosamente',
            'updated_count' => $updated,
            'unit_ids' => $unitIds
        ]);
    }

    /**
     * Eliminar múltiples unidades en bloque
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'required|exists:kiosk_units,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $unitIds = $request->unit_ids;

        // Validar que las unidades no estén vendidas
        $soldUnits = KioskUnit::whereIn('id', $unitIds)
            ->where('sold', true)
            ->pluck('id');

        if ($soldUnits->count() > 0) {
            return response()->json([
                'message' => 'No se pueden eliminar unidades vendidas',
                'sold_units' => $soldUnits
            ], 422);
        }

        $deleted = KioskUnit::whereIn('id', $unitIds)->delete();

        return response()->json([
            'message' => 'Unidades eliminadas exitosamente',
            'deleted_count' => $deleted,
            'unit_ids' => $unitIds
        ]);
    }
}
