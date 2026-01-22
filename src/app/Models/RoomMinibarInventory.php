<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomMinibarInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'room_id',
        'product_id',
        'initial_quantity',
        'current_quantity',
        'consumed_quantity',
        'recorded_at',
        'recorded_by',
        'record_type',
        'notes'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'initial_quantity' => 'integer',
        'current_quantity' => 'integer',
        'consumed_quantity' => 'integer'
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function product()
    {
        return $this->belongsTo(MinibarProduct::class, 'product_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function charge()
    {
        return $this->hasOne(ReservationMinibarCharge::class, 'inventory_record_id');
    }

    /**
     * Calcular cantidad consumida
     */
    public function calculateConsumed(): int
    {
        return max(0, $this->initial_quantity - $this->current_quantity);
    }

    /**
     * Actualizar cantidad consumida automáticamente
     */
    public function updateConsumedQuantity(): void
    {
        $this->consumed_quantity = $this->calculateConsumed();
        $this->save();
    }
}
