<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayPassCapacity extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'max_capacity',
        'consumed_capacity',
        'adult_price',
        'child_price',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'max_capacity' => 'integer',
        'consumed_capacity' => 'integer',
        'adult_price' => 'decimal:2',
        'child_price' => 'decimal:2',
    ];

    /**
     * Accessor para formatear la fecha como Y-m-d al serializar
     */
    protected $appends = [];

    /**
     * Serializar la fecha como Y-m-d para evitar problemas de timezone
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d');
    }

    /**
     * Convertir el modelo a array, formateando la fecha correctamente
     */
    public function toArray()
    {
        $array = parent::toArray();
        if (isset($array['date']) && $array['date']) {
            // Si la fecha es un objeto Carbon, formatearla
            if ($this->date instanceof \Carbon\Carbon) {
                $array['date'] = $this->date->format('Y-m-d');
            }
        }
        return $array;
    }

    /**
     * Obtener la capacidad disponible para una fecha
     */
    public function getAvailableCapacityAttribute()
    {
        return max(0, $this->max_capacity - $this->consumed_capacity);
    }

    /**
     * Verificar si hay capacidad disponible para un número de personas
     */
    public function hasCapacityFor($people)
    {
        return $this->available_capacity >= $people;
    }

    /**
     * Incrementar capacidad consumida
     */
    public function consumeCapacity($people)
    {
        $this->increment('consumed_capacity', $people);
    }

    /**
     * Decrementar capacidad consumida (cuando se cancela una reserva)
     */
    public function releaseCapacity($people)
    {
        $this->decrement('consumed_capacity', $people);
        if ($this->consumed_capacity < 0) {
            $this->consumed_capacity = 0;
            $this->save();
        }
    }

    /**
     * Obtener o crear capacidad para una fecha
     */
    public static function getOrCreateForDate($date, $defaultMaxCapacity = 0, $defaultAdultPrice = 0, $defaultChildPrice = 0)
    {
        return static::firstOrCreate(
            ['date' => $date],
            [
                'max_capacity' => $defaultMaxCapacity,
                'consumed_capacity' => 0,
                'adult_price' => $defaultAdultPrice,
                'child_price' => $defaultChildPrice,
            ]
        );
    }

    /**
     * Calcular el precio total para adultos y niños
     */
    public function calculatePrice($adults, $children)
    {
        return ($adults * $this->adult_price) + ($children * $this->child_price);
    }
}

