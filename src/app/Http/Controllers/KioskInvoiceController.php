<?php

namespace App\Http\Controllers;

use App\Models\KioskInvoice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class KioskInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskInvoice::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payed' => 'required|boolean',
            'payment_code' => 'required|unique:kiosk_invoices',
            'payment_type_id' => 'required|exists:payment_types,id',
        ]);

        $kioskInvoice = KioskInvoice::create($request->all());
        return response()->json($kioskInvoice, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(KioskInvoice $kioskInvoice)
    {
        return $kioskInvoice;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskInvoice $kioskInvoice)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payed' => 'required|boolean',
            'payment_code' => 'required|unique:kiosk_invoices,payment_code,' . $kioskInvoice->id,
            'payment_type_id' => 'required|exists:payment_types,id',
        ]);

        $kioskInvoice->update($request->all());
        return response()->json($kioskInvoice, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskInvoice $kioskInvoice)
    {
        $kioskInvoice->delete();
        return response()->json(null, 204);
    }
}