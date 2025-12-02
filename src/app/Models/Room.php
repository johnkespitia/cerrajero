<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'number', // Número o nombre de la habitación
        'name', // Nombre alternativo
        'capacity', // Aforo
        'max_capacity',
        'extra_bed_capacity',
        'room_price', // Valor de la habitación
        'extra_person_price',
        'extra_bed_price',
        'status',
        'description', // Descripción
        'amenities',
        'active'
    ];

    protected $casts = [
        'amenities' => 'array',
        'room_price' => 'decimal:2',
        'extra_person_price' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function getDisplayNameAttribute()
    {
        return $this->number ? "Habitación {$this->number}" : ($this->name ?? "Habitación #{$this->id}");
    }

    public function isAvailable($checkIn, $checkOut)
    {
        if ($this->status !== 'available' || !$this->active) {
            return false;
        }

        $conflictingReservations = $this->reservations()
            ->where('status', '!=', 'cancelled')
            ->where(function($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                          ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->exists();

        return !$conflictingReservations;
    }

    public function canAccommodate($adults, $children = 0, $infants = 0)
    {
        // Solo contar adultos y niños para la capacidad; los bebés no ocupan cama completa
        $totalGuests = $adults + $children;
        return $totalGuests <= $this->capacity; // Usar capacity (aforo) en lugar de max_capacity
    }
}
