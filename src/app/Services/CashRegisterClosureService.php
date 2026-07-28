<?php

namespace App\Services;

use App\Models\CashRegisterClosure;
use App\Models\KioskInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Vincula facturas de kiosko con el cierre de caja del cajero.
 *
 * Puntos frágiles conocidos (revisar antes de prod / multi-caja):
 * - El cierre es por user_id + día; una factura solo se asigna al cajero autenticado.
 * - Si el día ya tiene cierre cerrado, NO se crea otro abierto (unique user_id+date+closed
 *   solo permite un closed=true; un segundo abierto no se podría cerrar luego).
 * - Las facturas no tienen created_by: el rescate de huérfanas asigna todas las del día
 *   al cierre abierto del usuario que consulta/cierra (OK en caja única).
 * - Facturas de checkout de reserva / FEV de hospedaje NO entran aquí (solo KioskInvoice).
 * - Medios de pago con nombre no reconocido no suman a cash/card/transfer (sí a total_sales).
 */
class CashRegisterClosureService
{
    /**
     * Obtiene el cierre abierto del usuario para la fecha, o lo crea si aún no hay ninguno.
     * Si solo existe uno cerrado, no crea otro (regla de negocio + límite del unique index).
     */
    public function ensureOpenClosure(int $userId, $date = null): ?CashRegisterClosure
    {
        $dateString = $this->normalizeDate($date);

        $open = CashRegisterClosure::where('user_id', $userId)
            ->whereDate('closure_date', $dateString)
            ->where('closed', false)
            ->orderByDesc('created_at')
            ->first();

        if ($open) {
            return $open;
        }

        $closedExists = CashRegisterClosure::where('user_id', $userId)
            ->whereDate('closure_date', $dateString)
            ->where('closed', true)
            ->exists();

        if ($closedExists) {
            Log::warning('CashRegisterClosure: venta/consulta con caja ya cerrada; no se crea un segundo cierre abierto', [
                'user_id' => $userId,
                'date' => $dateString,
            ]);
            return null;
        }

        return CashRegisterClosure::firstOrCreate(
            [
                'user_id' => $userId,
                'closure_date' => $dateString,
                'closed' => false,
            ],
            [
                'opening_balance' => 0,
            ]
        );
    }

    /**
     * Asigna la factura al cierre abierto del usuario (creándolo si hace falta).
     * No reasigna si ya tiene closure_id.
     */
    public function assignInvoice(KioskInvoice $invoice, int $userId, $date = null): ?CashRegisterClosure
    {
        if ($invoice->closure_id) {
            return $invoice->closure()->first();
        }

        $closure = $this->ensureOpenClosure($userId, $date);
        if (!$closure) {
            Log::warning('CashRegisterClosure: factura kiosko sin cierre abierto disponible', [
                'invoice_id' => $invoice->id,
                'user_id' => $userId,
            ]);
            return null;
        }

        $invoice->closure_id = $closure->id;
        $invoice->save();

        return $closure;
    }

    /**
     * Adjunta facturas del día sin closure_id a este cierre.
     * Debe llamarse solo sobre cierres abiertos (o justo antes de marcar closed en close()).
     */
    public function attachOrphanInvoices(CashRegisterClosure $closure): int
    {
        $dateString = $this->normalizeDate($closure->closure_date);

        return KioskInvoice::whereNull('closure_id')
            ->whereNull('cancelled_at')
            ->whereDate('created_at', $dateString)
            ->update(['closure_id' => $closure->id]);
    }

    /**
     * Recalcula totales del cierre tras vincular facturas.
     */
    public function refreshTotals(CashRegisterClosure $closure): CashRegisterClosure
    {
        $closure->calculateTotals();
        return $closure->fresh([
            'invoices.customer',
            'invoices.payment_type',
        ]) ?? $closure;
    }

    private function normalizeDate($date): string
    {
        if ($date === null) {
            return Carbon::today()->toDateString();
        }

        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return Carbon::parse($date)->toDateString();
    }
}
