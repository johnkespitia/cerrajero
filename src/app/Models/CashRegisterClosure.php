<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CashRegisterClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'closure_date',
        'opening_balance',
        'closing_balance',
        'total_sales',
        'total_cash',
        'total_card',
        'total_credit',
        'total_transfer',
        'total_invoices',
        'total_voided_invoices',
        'observations',
        'closed',
        'closed_by',
        'closed_at'
    ];

    protected $casts = [
        'closure_date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_card' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'total_transfer' => 'decimal:2',
        'closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que creó el cierre
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el usuario que cerró la caja
     */
    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Relación con las facturas del cierre
     */
    public function invoices()
    {
        return $this->hasMany(KioskInvoice::class, 'closure_id');
    }

    /**
     * Scope para obtener cierres abiertos
     */
    public function scopeOpen($query)
    {
        return $query->where('closed', false);
    }

    /**
     * Scope para obtener cierres cerrados
     */
    public function scopeClosed($query)
    {
        return $query->where('closed', true);
    }

    /**
     * Scope para obtener cierres de una fecha específica
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('closure_date', $date);
    }

    /**
     * Calcular totales de las facturas
     * Separa las compras por medio de pago y solo cuenta el efectivo para el balance esperado
     * Los servicios a crédito se registran en total_credit pero NO se suman al balance esperado
     * Canceladas y pendientes walk-in (sin crédito / sin pagar) no entran a los buckets de dinero
     */
    public function calculateTotals()
    {
        // Solo facturas no canceladas con impacto en caja: pagadas o crédito a habitación pendiente
        $invoices = $this->invoices()
            ->whereNull('cancelled_at')
            ->with(['payment_type', 'details'])
            ->get()
            ->filter(function ($invoice) {
                if ($invoice->payed) {
                    return true;
                }
                // Crédito a habitación pendiente → total_credit
                return $invoice->payment_type
                    && ($invoice->payment_type->credit === true || $invoice->payment_type->credit === 1);
            });

        $this->total_invoices = $invoices->count();
        $this->total_sales = $invoices->sum(function ($invoice) {
            return $invoice->payableTotal();
        });

        $this->total_cash = 0;
        $this->total_card = 0;
        $this->total_credit = 0;
        $this->total_transfer = 0;

        $paymentTypes = $invoices->groupBy('payment_type_id');

        foreach ($paymentTypes as $paymentTypeId => $typeInvoices) {
            $paymentType = PaymentType::find($paymentTypeId);
            if (!$paymentType) {
                continue;
            }

            $total = $typeInvoices->sum(function ($invoice) {
                return $invoice->payableTotal();
            });

            // PRIORIDAD 1: crédito a habitación pendiente → solo total_credit
            if (($paymentType->credit === true || $paymentType->credit === 1) && !$typeInvoices->first()->payed) {
                $creditTotal = $typeInvoices->filter(fn ($inv) => !$inv->payed)->sum(function ($invoice) {
                    return $invoice->payableTotal();
                });
                $this->total_credit += $creditTotal;
                continue;
            }

            // Solo facturas pagadas (no crédito) entran a cash/card/transfer
            $paidTotal = $typeInvoices->filter(fn ($inv) => (bool) $inv->payed)->sum(function ($invoice) {
                return $invoice->payableTotal();
            });
            if ($paidTotal <= 0) {
                continue;
            }

            if ($paymentType->credit === true || $paymentType->credit === 1) {
                continue;
            }

            $paymentTypeName = strtolower(trim($paymentType->name));

            if (strpos($paymentTypeName, 'efectivo') !== false || strpos($paymentTypeName, 'cash') !== false) {
                $this->total_cash += $paidTotal;
            } elseif (strpos($paymentTypeName, 'tarjeta') !== false || strpos($paymentTypeName, 'card') !== false ||
                      strpos($paymentTypeName, 'tarjeta de') !== false || strpos($paymentTypeName, 'debito') !== false ||
                      strpos($paymentTypeName, 'débito') !== false) {
                $this->total_card += $paidTotal;
            } elseif (strpos($paymentTypeName, 'transferencia') !== false || strpos($paymentTypeName, 'transfer') !== false) {
                $this->total_transfer += $paidTotal;
            }
        }

        $this->closing_balance = $this->opening_balance + $this->total_cash;
        $this->save();
    }
}
