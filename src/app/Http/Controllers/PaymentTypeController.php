<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PaymentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return PaymentType::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:payment_types',
            "credit" => 'required|boolean',
            "calculator" => 'required|boolean',
            'active' => 'boolean',
        ]);

        $paymentType = PaymentType::create($request->all());
        return response()->json($paymentType, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentType $paymentType)
    {
        return $paymentType;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentType $paymentType)
    {
        $request->validate([
            'name' => 'unique:payment_types,name,' . $paymentType->id,
            "credit" => 'boolean',
            "calculator" => 'boolean',
            'active' => 'boolean',
        ]);

        $paymentType->update($request->all());
        return response()->json($paymentType, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentType $paymentType)
    {
        $paymentType->delete();
        return response()->json(null, 204);
    }
}