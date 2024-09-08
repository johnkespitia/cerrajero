<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Tax::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:payment_types',
            'active' => 'boolean',
        ]);

        $tax = Tax::create($request->all());
        return response()->json($tax, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Tax $tax)
    {
        return $tax;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tax $tax)
    {
        $request->validate([
            'name' => 'required|unique:payment_types,name,' . $tax->id,
            'active' => 'boolean',
        ]);

        $tax->update($request->all());
        return response()->json($tax, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tax $tax)
    {
        $tax->delete();
        return response()->json(null, 204);
    }
}
