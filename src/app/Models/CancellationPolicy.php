<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'policy_type',
        'cancellation_days_before',
        'penalty_percentage',
        'penalty_fee',
        'apply_to',
        'room_type_id',
        'reservation_type',
        'is_default',
        'active',
    ];

    protected $casts = [
        'cancellation_days_before' => 'integer',
        'penalty_percentage' => 'decimal:2',
        'penalty_fee' => 'decimal:2',
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Relación con tipo de habitación
     */
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Relación con reservas
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Scope para políticas activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para políticas por defecto
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope para políticas que aplican a todas
     */
    public function scopeForAll($query)
    {
        return $query->where('apply_to', 'all');
    }

    /**
     * Scope para políticas por tipo de habitación
     */
    public function scopeForRoomType($query, $roomTypeId)
    {
        return $query->where('apply_to', 'room_type')
            ->where('room_type_id', $roomTypeId);
    }

    /**
     * Scope para políticas por tipo de reserva
     */
    public function scopeForReservationType($query, $reservationType)
    {
        return $query->where('apply_to', 'reservation_type')
            ->where('reservation_type', $reservationType);
    }

    /**
     * Obtener política aplicable para una reserva
     */
    public static function getApplicablePolicy($roomTypeId = null, $reservationType = null)
    {
        // Primero buscar por tipo de habitación
        if ($roomTypeId) {
            $policy = self::active()
                ->forRoomType($roomTypeId)
                ->first();
            
            if ($policy) {
                return $policy;
            }
        }

        // Luego buscar por tipo de reserva
        if ($reservationType) {
            $policy = self::active()
                ->forReservationType($reservationType)
                ->first();
            
            if ($policy) {
                return $policy;
            }
        }

        // Finalmente buscar política por defecto
        $policy = self::active()
            ->default()
            ->first();

        if ($policy) {
            return $policy;
        }

        // Si no hay política por defecto, buscar cualquier política para todas
        return self::active()
            ->forAll()
            ->first();
    }

    /**
     * Calcular fecha límite de cancelación
     */
    public function calculateDeadline($checkInDate)
    {
        if (!$this->cancellation_days_before) {
            return null;
        }

        $checkIn = \Carbon\Carbon::parse($checkInDate);
        return $checkIn->subDays($this->cancellation_days_before);
    }

    /**
     * Verificar si se puede cancelar sin penalización
     */
    public function canCancelFree($checkInDate)
    {
        if ($this->policy_type === 'non_refundable') {
            return false;
        }

        if ($this->policy_type === 'free') {
            return true;
        }

        if (!$this->cancellation_days_before) {
            return true; // Si no hay días especificados, se puede cancelar gratis
        }

        $deadline = $this->calculateDeadline($checkInDate);
        return now()->lte($deadline);
    }
}

