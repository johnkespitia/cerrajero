<?php

namespace App\Http\Controllers;

use App\Models\KioskInvoiceDetail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class KioskInvoiceDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskInvoiceDetail::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'kiosk_invoices_id' => 'required|exists:kiosk_invoices,id',
            'kiosk_units_id' => 'required|exists:kiosk_units,id',
            'price' => 'required|numeric|min:0',
        ]);

        $kioskInvoiceDetail = KioskInvoiceDetail::create($request->all());
        return response()->json($kioskInvoiceDetail, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(KioskInvoiceDetail $kioskInvoiceDetail)
    {
        return $kioskInvoiceDetail;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskInvoiceDetail $kioskInvoiceDetail)
    {
        $request->validate([
            'kiosk_invoices_id' => 'required|exists:kiosk_invoices,id',
            'kiosk_units_id' => 'required|exists:kiosk_units,id',
            'price' => 'required|numeric|min:0',
        ]);

        $kioskInvoiceDetail->update($request->all());
        return response()->json($kioskInvoiceDetail, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskInvoiceDetail $kioskInvoiceDetail)
    {
        $kioskInvoiceDetail->delete();
        return response()->json(null, 204);
    }
}