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
     */
    public function calculateTotals()
    {
        // Cargar facturas con la relación payment_type para acceder al campo credit
        $invoices = $this->invoices()->with('payment_type')->get();
        
        $this->total_invoices = $invoices->count();
        $this->total_sales = $invoices->sum(function($invoice) {
            return $invoice->details->sum('price');
        });

        // Inicializar totales en 0
        $this->total_cash = 0;
        $this->total_card = 0;
        $this->total_credit = 0;
        $this->total_transfer = 0;

        // Agrupar por tipo de pago y sumar los totales
        $paymentTypes = $invoices->groupBy('payment_type_id');

        foreach ($paymentTypes as $paymentTypeId => $typeInvoices) {
            $paymentType = PaymentType::find($paymentTypeId);
            if (!$paymentType) continue;

            // Calcular el total de este tipo de pago sumando todas las facturas
            $total = $typeInvoices->sum(function($invoice) {
                return $invoice->details->sum('price');
            });

            // PRIORIDAD 1: Si el PaymentType tiene credit = true, es un servicio a crédito
            // Los servicios a crédito NO se suman al balance esperado (solo efectivo)
            if ($paymentType->credit === true || $paymentType->credit === 1) {
                $this->total_credit += $total;
                continue; // No clasificar por nombre si es crédito
            }

            // PRIORIDAD 2: Si no es crédito, clasificar por nombre del tipo de pago
            $paymentTypeName = strtolower(trim($paymentType->name));
            
            // Clasificar y SUMAR (no sobrescribir) según el tipo de pago
            if (strpos($paymentTypeName, 'efectivo') !== false || strpos($paymentTypeName, 'cash') !== false) {
                $this->total_cash += $total;
            } elseif (strpos($paymentTypeName, 'tarjeta') !== false || strpos($paymentTypeName, 'card') !== false || 
                      strpos($paymentTypeName, 'tarjeta de') !== false || strpos($paymentTypeName, 'debito') !== false ||
                      strpos($paymentTypeName, 'débito') !== false) {
                $this->total_card += $total;
            } elseif (strpos($paymentTypeName, 'transferencia') !== false || strpos($paymentTypeName, 'transfer') !== false) {
                $this->total_transfer += $total;
            } else {
                // Si no coincide con ningún tipo conocido, no se suma a ningún total específico
                // pero sí se cuenta en total_sales (que ya se calculó arriba)
            }
        }

        // El balance esperado solo incluye el efectivo (balance inicial + total en efectivo)
        // Los otros medios de pago se registran pero no se suman al balance físico
        $this->closing_balance = $this->opening_balance + $this->total_cash;
        $this->save();
    }
}





