<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeMeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'meal_type',
        'meal_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'meal_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mealItems()
    {
        return $this->hasMany(EmployeeMealItem::class, 'employee_meal_id');
    }

    /**
     * Obtener costo total del inventario consumido
     */
    public function getTotalCost(): float
    {
        return (float) $this->mealItems()->sum('inventory_cost');
    }

    /**
     * Obtener costo de inventario (alias)
     */
    public function getInventoryCost(): float
    {
        return $this->getTotalCost();
    }
}
