<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMealItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_meal_id',
        'recipe_id',
        'quantity',
        'measure_id',
        'inventory_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'inventory_cost' => 'decimal:2',
    ];

    public function employeeMeal()
    {
        return $this->belongsTo(EmployeeMeal::class, 'employee_meal_id');
    }

    public function recipe()
    {
        return $this->belongsTo(KitchenRecipe::class, 'recipe_id');
    }

    public function measure()
    {
        return $this->belongsTo(InventoryMeasure::class, 'measure_id');
    }

    /**
     * Calcular costo de inventario consumido
     * Este método se llamará después de descontar el inventario
     */
    public function calculateInventoryCost(): float
    {
        // El costo se calcula en el servicio EmployeeMealService
        // después de descontar el inventario usando FIFO
        return (float) $this->inventory_cost;
    }
}
