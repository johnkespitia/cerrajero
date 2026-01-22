<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomMinibarStock extends Model
{
    use HasFactory;

    protected $table = 'room_minibar_stock';

    protected $fillable = [
        'room_id',
        'product_id',
        'standard_quantity',
        'current_quantity',
        'last_restocked_at',
        'last_restocked_by',
        'notes',
        'active'
    ];

    protected $casts = [
        'standard_quantity' => 'integer',
        'current_quantity' => 'integer',
        'last_restocked_at' => 'datetime',
        'active' => 'boolean'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function product()
    {
        return $this->belongsTo(MinibarProduct::class, 'product_id');
    }

    public function lastRestockedBy()
    {
        return $this->belongsTo(User::class, 'last_restocked_by');
    }

    public function restockingLogs()
    {
        return $this->hasMany(MinibarRestockingLog::class, 'room_id', 'room_id')
                    ->where('product_id', $this->product_id);
    }

    /**
     * Verificar si necesita reposición
     */
    public function needsRestocking(): bool
    {
        return $this->current_quantity < $this->standard_quantity;
    }

    /**
     * Calcular cantidad faltante
     */
    public function getMissingQuantityAttribute(): int
    {
        return max(0, $this->standard_quantity - $this->current_quantity);
    }
}
