<?php

namespace App\Http\Controllers;

use App\Models\KioskInvoice;
use App\Models\KioskUnit;
use App\Models\KioskInvoiceDetail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;


class KioskInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskInvoice::with(["customer","payment_type","details.kiosk_unit.product.tax","details.kiosk_unit.product.category"])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'payed' => 'required|boolean',
                'payment_code' => 'required|unique:kiosk_invoices',
                'payment_type_id' => 'required|exists:payment_types,id',
                'units'=> 'required|array',
                'units.*.kiosk_units_id' => 'required|exists:kiosk_units,id',
                'units.*.price' => 'required|numeric|min:0',
            ]);
            $kioskInvoice = KioskInvoice::create($request->all());
            $units = $request->get("units");
            foreach ($units as $key => $unit) {
                $unitModel = KioskUnit::find($unit['kiosk_units_id']);
                $unit['kiosk_invoices_id'] = $kioskInvoice->id;
                KioskInvoiceDetail::create($unit);
                $unitModel->sold = true;
                $unitModel->save();
            }
            $kioskInvoice->details;
            $kioskInvoice->payment_type;
            $kioskInvoice->customer;
            return response()->json($kioskInvoice, 201);
        }catch(ValidationException $ve){
            return response()->json([
                'errors' => $ve->errors()
            ], 422);
        }

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
