<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationMealConsumption extends Model
{
    use HasFactory;

    protected $table = 'reservation_meal_consumption';

    protected $fillable = [
        'reservation_id',
        'order_id',
        'meal_type',
        'quantity_consumed',
        'is_included',
        'is_additional',
        'consumption_date',
    ];

    protected $casts = [
        'is_included' => 'boolean',
        'is_additional' => 'boolean',
        'consumption_date' => 'date',
        'quantity_consumed' => 'integer',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Obtener consumo incluido
     */
    public static function getIncludedConsumption($reservationId, $mealType = null, $date = null)
    {
        $query = static::where('reservation_id', $reservationId)
            ->where('is_included', true);
        
        if ($mealType) {
            $query->where('meal_type', $mealType);
        }
        
        if ($date) {
            $query->whereDate('consumption_date', $date);
        }
        
        return $query->sum('quantity_consumed');
    }

    /**
     * Obtener consumo adicional
     */
    public static function getAdditionalConsumption($reservationId, $mealType = null, $date = null)
    {
        $query = static::where('reservation_id', $reservationId)
            ->where('is_additional', true);
        
        if ($mealType) {
            $query->where('meal_type', $mealType);
        }
        
        if ($date) {
            $query->whereDate('consumption_date', $date);
        }
        
        return $query->sum('quantity_consumed');
    }
}
