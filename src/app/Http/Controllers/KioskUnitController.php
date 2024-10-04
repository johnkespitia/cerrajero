<?php

namespace App\Http\Controllers;

use App\Models\KioskUnit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;


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
        ]);

        $kioskUnit->update($request->all());
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
}
