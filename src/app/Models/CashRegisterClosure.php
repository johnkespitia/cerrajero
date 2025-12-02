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
     */
    public function calculateTotals()
    {
        $invoices = $this->invoices;
        
        $this->total_invoices = $invoices->count();
        $this->total_sales = $invoices->sum(function($invoice) {
            return $invoice->details->sum('price');
        });

        // Agrupar por tipo de pago
        $paymentTypes = $invoices->groupBy('payment_type_id');
        
        $this->total_cash = 0;
        $this->total_card = 0;
        $this->total_credit = 0;
        $this->total_transfer = 0;

        foreach ($paymentTypes as $paymentTypeId => $typeInvoices) {
            $paymentType = PaymentType::find($paymentTypeId);
            if (!$paymentType) continue;

            $total = $typeInvoices->sum(function($invoice) {
                return $invoice->details->sum('price');
            });

            $paymentTypeName = strtolower($paymentType->name);
            
            if (strpos($paymentTypeName, 'efectivo') !== false || strpos($paymentTypeName, 'cash') !== false) {
                $this->total_cash = $total;
            } elseif (strpos($paymentTypeName, 'tarjeta') !== false || strpos($paymentTypeName, 'card') !== false) {
                $this->total_card = $total;
            } elseif (strpos($paymentTypeName, 'credito') !== false || strpos($paymentTypeName, 'credit') !== false) {
                $this->total_credit = $total;
            } elseif (strpos($paymentTypeName, 'transferencia') !== false || strpos($paymentTypeName, 'transfer') !== false) {
                $this->total_transfer = $total;
            }
        }

        $this->closing_balance = $this->opening_balance + $this->total_cash;
        $this->save();
    }
}





