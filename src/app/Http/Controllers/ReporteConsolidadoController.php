<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\KioskInvoice;
use App\Models\CashRegisterClosure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteConsolidadoController extends Controller
{
    /**
     * Consolidación de reportes por periodo (reservas + kiosko + cierres de caja).
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function consolidado(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $fromDate = Carbon::parse($validated['date_from'])->startOfDay();
        $toDate  = Carbon::parse($validated['date_to'])->endOfDay();

        // 1. Reservas en el periodo (check_in o check_out dentro de la ventana)
        $reservations = Reservation::where(function ($q) use ($fromDate, $toDate) {
            $q->where('check_in_date', '>=', $fromDate)
              ->orWhere('check_out_date', '<=', $toDate);
        })->with(['customer', 'room'])->get();

        // 2. Facturas del kiosko (pagadas o en crédito a habitación, no canceladas)
        $kioskInvoices = KioskInvoice::whereNull('cancelled_at')
            ->where(function ($q) use ($fromDate, $toDate) {
                $q->with('reservation')->has('reservation', function ($subQuery) use ($fromDate, $toDate) {
                    $subQuery->where('check_in_date', '>=', $fromDate)
                              ->orWhere('check_out_date', '<=', $toDate);
                });
            })->get();

        // 3. Cierres de caja en el periodo
        $closures = CashRegisterClosure::where('closure_date', 'in', [$fromDate->toDateString(), $toDate->toDateString()])
            ->with(['invoices'])->get();

        return response()->json([
            'periodo' => [
                'desde' => $fromDate->format('Y-m-d'),
                'hasta'  => $toDate->format('Y-m-d'),
            ],
            'reservas' => [
                'total_reservas'    => $reservations->count(),
                'guests_total'      => $reservations->sum(function ($r) { return $r->getNightsAttribute(); }),
                'final_price_total' => $reservations->sum('final_price'),
            ],
            'kiosk_invoices' => [
                'total_facturas'    => $kioskInvoices->count(),
                'pagadas'           => $kioskInvoices->filter(fn ($i) => (bool) $i->payed)->count(),
                'pendientes'        => $kioskInvoices->filter(fn ($i) => $i->isPending())->count(),
                'total_pagado'      => $kioskInvoices->filter(fn ($i) => (bool) $i->payed)->sum('payableTotal'),
            ],
            'closures' => [
                'total_cierres'    => $closures->count(),
                'total_vendido'    => $closures->sum('total_sales'),
                'total_efectivo'   => $closures->sum('total_cash'),
                'total_tarjeta'   => $closures->sum('total_card'),
                'total_credential'=> $closures->sum('total_credit'),
            ],
        ]);
    }

    /**
     * Historial detallado de reportes consolidados por fecha individual.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function historial(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        // Reservas con check_in o check_out en esa fecha exacta
        $reservations = Reservation::where(function ($q) use ($date) {
            $q->where('check_in_date', $date)
              ->orWhere('check_out_date', $date);
        })->with(['customer', 'room'])->get();

        // Facturas kiosko en esa fecha (canceladas excluidas)
        $kioskInvoices = KioskInvoice::whereNull('cancelled_at')
            ->where(function ($q) use ($date) {
                $q->with('reservation')->has('reservation', function ($subQuery) use ($date) {
                    $subQuery->where('check_in_date', $date)
                              ->orWhere('check_out_date', $date);
                });
            })->get();

        // Cierre de caja en esa fecha
        $closures = CashRegisterClosure::whereDate('closure_date', $date)
            ->with(['invoices'])->get();

        return response()->json([
            'fecha' => $date,
            'reservas' => [
                'total_reservas'    => $reservations->count(),
                'guests_total'      => $reservations->sum(fn ($r) => $r->getNightsAttribute()),
                'final_price_total' => $reservations->sum('final_price'),
            ],
            'kiosk_invoices' => [
                'total_facturas'    => $kioskInvoices->count(),
                'pagadas'           => $kioskInvoices->filter(fn ($i) => (bool) $i->payed)->count(),
                'pendientes'        => $kioskInvoices->filter(fn ($i) => $i->isPending())->count(),
                'total_pagado'      => $kioskInvoices->filter(fn ($i) => (bool) $i->payed)->sum('payableTotal'),
            ],
            'closures' => [
                'total_cierres'    => $closures->count(),
                'total_vendido'    => $closures->sum('total_sales'),
                'total_efectivo'   => $closures->sum('total_cash'),
                'total_tarjeta'   => $closures->sum('total_card'),
                'total_credential'=> $closures->sum('total_credit'),
            ],
        ]);
    }
}
