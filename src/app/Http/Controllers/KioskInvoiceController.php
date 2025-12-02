<?php

namespace App\Http\Controllers;

use App\Models\KioskInvoice;
use App\Models\PaymentType;
use App\Models\KioskUnit;
use App\Models\KioskInvoiceDetail;
use App\Models\CashRegisterClosure;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


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
                'payment_code' => 'required',
                'payment_type_id' => 'required|exists:payment_types,id',
                'units'=> 'required|array',
                'units.*.kiosk_units_id' => 'required|exists:kiosk_units,id',
                'units.*.price' => 'required|numeric|min:0',
                'electronic_invoice' => 'required|boolean',
                'payed_value' => 'numeric'
            ]);
            $requestBody = $request->all();
            $paymentType = PaymentType::find($requestBody['payment_type_id']);
            $requestBody['payed'] = !$paymentType->credit;
            $kioskInvoice = KioskInvoice::create($requestBody);
            $units = $request->get("units");
            $total_invoice = 0;
            foreach ($units as $key => $unit) {
                $unitModel = KioskUnit::find($unit['kiosk_units_id']);
                $unit['kiosk_invoices_id'] = $kioskInvoice->id;
                $unit_saved = KioskInvoiceDetail::create($unit);
                $unitModel->sold = true;
                $unitModel->save();
                $total_invoice += $unitModel->product->sale_price;
            }
            if($request->get('payed_value') > 0){
                $kioskInvoice->remain_money = $kioskInvoice->payed_value - $total_invoice;
                $kioskInvoice->save();
            }

            // Asignar a cierre de caja abierto del día
            $user = $request->user();
            $today = Carbon::today();

            $closure = CashRegisterClosure::where('user_id', $user->id)
                ->whereDate('closure_date', $today)
                ->where('closed', false)
                ->first();

            if ($closure) {
                $kioskInvoice->closure_id = $closure->id;
                $kioskInvoice->save();
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
