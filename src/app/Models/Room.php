<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        // Si la habitación no está activa, no está disponible
        if (!$this->active) {
            return false;
        }

        // Convertir a Carbon si no lo son y normalizar a fecha (sin hora)
        if (!$checkIn instanceof Carbon) {
            $checkIn = Carbon::parse($checkIn)->startOfDay();
        } else {
            $checkIn = $checkIn->copy()->startOfDay();
        }
        
        if (!$checkOut instanceof Carbon) {
            $checkOut = Carbon::parse($checkOut)->startOfDay();
        } else {
            $checkOut = $checkOut->copy()->startOfDay();
        }

        // Si la habitación está en estado 'occupied', verificar si realmente debería estarlo
        // (es decir, si hay reservas activas que justifiquen ese estado)
        if ($this->status === 'occupied') {
            $today = Carbon::today();
            
            // Verificar si hay reservas en checked_in que ya pasaron su fecha de check-out
            $expiredCheckedIn = $this->reservations()
                ->where('status', 'checked_in')
                ->where('check_out_date', '<', $today->format('Y-m-d'))
                ->exists();
            
            // Si hay reservas en checked_in que ya pasaron su fecha, liberar la habitación automáticamente
            if ($expiredCheckedIn) {
                $this->update(['status' => 'available']);
            } else {
                // Si hay reservas activas en checked_in dentro del período solicitado, no está disponible
                $activeCheckedIn = $this->reservations()
                    ->where('status', 'checked_in')
                    ->where(function($query) use ($checkIn, $checkOut) {
                        $query->where(function($q) use ($checkIn, $checkOut) {
                            $q->where('check_in_date', '<', $checkOut->format('Y-m-d'))
                              ->where('check_out_date', '>=', $checkIn->format('Y-m-d'));
                        });
                    })
                    ->exists();
                
                if ($activeCheckedIn) {
                    return false;
                }
            }
        }
        
        // Si la habitación está en mantenimiento o fuera de servicio, no está disponible
        if ($this->status === 'maintenance' || $this->status === 'out_of_order') {
            return false;
        }

        // Lógica correcta de solapamiento:
        // Dos períodos se solapan si:
        // - El inicio de la reserva existente es menor que el fin del período solicitado
        // - Y el fin de la reserva existente es mayor que el inicio del período solicitado
        // Nota: Si una reserva termina el día X y otra empieza el día X, NO se solapan
        // Solo considerar reservas confirmadas o con check-in (no canceladas ni con check-out)
        $conflictingReservations = $this->reservations()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->where(function($query) use ($checkIn, $checkOut) {
                $query->where(function($q) use ($checkIn, $checkOut) {
                    // La reserva existente empieza antes de que termine el período solicitado
                    // Y la reserva existente termina después de que empiece el período solicitado
                    $q->where('check_in_date', '<', $checkOut->format('Y-m-d'))
                      ->where('check_out_date', '>', $checkIn->format('Y-m-d'));
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
