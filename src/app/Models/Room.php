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

        // Si hay mantenimientos activos que requieren sacar la habitación de servicio, no está disponible
        if ($this->hasMaintenanceInProgress()) {
            return false;
        }

        // Verificar mantenimientos urgentes activos
        $urgentMaintenance = $this->maintenanceRequests()
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'on_hold'])
            ->whereIn('priority', ['high', 'urgent'])
            ->whereIn('issue_type', ['damage', 'repair'])
            ->exists();
        
        if ($urgentMaintenance) {
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

    public function inventoryAssignments()
    {
        return $this->morphMany(RoomInventoryAssignment::class, 'assignable');
    }

    public function activeInventoryAssignments()
    {
        return $this->morphMany(RoomInventoryAssignment::class, 'assignable')
                    ->where('active', true);
    }

    public function inventoryHistory()
    {
        return $this->morphMany(RoomInventoryHistory::class, 'assignable');
    }

    // Relaciones para aseo y mantenimiento
    public function cleaningRecords()
    {
        return $this->morphMany(CleaningRecord::class, 'cleanable');
    }

    public function latestCleaningRecord()
    {
        return $this->morphOne(CleaningRecord::class, 'cleanable')->latestOfMany('cleaning_date');
    }

    public function maintenanceRequests()
    {
        return $this->morphMany(MaintenanceRequest::class, 'maintainable');
    }

    public function maintenanceWorks()
    {
        return $this->morphMany(MaintenanceWork::class, 'maintainable');
    }

    public function cleaningSchedule()
    {
        return $this->morphOne(CleaningSchedule::class, 'cleanable');
    }

    public function getDaysSinceLastCleaningAttribute()
    {
        $lastCleaning = $this->latestCleaningRecord;
        if (!$lastCleaning) {
            return null;
        }
        return Carbon::now()->diffInDays($lastCleaning->cleaning_date);
    }

    // Relaciones para minibar
    public function minibarStock()
    {
        return $this->hasMany(RoomMinibarStock::class)
                    ->where('active', true);
    }

    public function minibarStockForProduct($productId)
    {
        return $this->hasOne(RoomMinibarStock::class)
                    ->where('product_id', $productId)
                    ->where('active', true);
    }

    public function getMinibarStockNeedingRestockAttribute()
    {
        return $this->minibarStock()
                    ->whereColumn('current_quantity', '<', 'standard_quantity')
                    ->get();
    }

    /**
     * Verificar si la habitación tiene mantenimientos activos
     * Mantenimientos activos: pending, assigned, in_progress, on_hold
     */
    public function hasActiveMaintenance()
    {
        return $this->maintenanceRequests()
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'on_hold'])
            ->exists();
    }

    /**
     * Verificar si la habitación tiene mantenimientos en progreso
     * (trabajo activo que requiere sacar la habitación de servicio)
     */
    public function hasMaintenanceInProgress()
    {
        return $this->maintenanceRequests()
            ->where('status', 'in_progress')
            ->whereIn('issue_type', ['damage', 'repair']) // Solo daños y reparaciones requieren sacar de servicio
            ->exists();
    }

    /**
     * Actualizar el estado de la habitación basado en mantenimientos activos
     */
    public function updateStatusBasedOnMaintenance()
    {
        // Si hay mantenimiento en progreso (daños/reparaciones), poner en maintenance
        if ($this->hasMaintenanceInProgress()) {
            if ($this->status !== 'maintenance') {
                $this->update(['status' => 'maintenance']);
            }
        } 
        // Si hay otros mantenimientos activos pero no en progreso, verificar si debe estar en maintenance
        elseif ($this->hasActiveMaintenance()) {
            // Para mantenimientos preventivos o inspecciones, solo cambiar si es urgente
            $urgentMaintenance = $this->maintenanceRequests()
                ->whereIn('status', ['pending', 'assigned', 'on_hold'])
                ->whereIn('priority', ['high', 'urgent'])
                ->whereIn('issue_type', ['damage', 'repair'])
                ->exists();
            
            if ($urgentMaintenance && $this->status !== 'maintenance') {
                $this->update(['status' => 'maintenance']);
            }
        }
        // Si no hay mantenimientos activos y está en maintenance, restaurar a available
        elseif ($this->status === 'maintenance' && !$this->hasActiveMaintenance()) {
            // Verificar que no esté ocupada por una reserva
            $hasActiveReservation = $this->reservations()
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->where('check_out_date', '>=', Carbon::today()->format('Y-m-d'))
                ->exists();
            
            if (!$hasActiveReservation) {
                $this->update(['status' => 'available']);
            }
        }
    }
}
