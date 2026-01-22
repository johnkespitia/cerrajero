<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationMinibarCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'inventory_record_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
        'recorded_at',
        'recorded_by',
        'record_type',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'recorded_at' => 'datetime'
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function inventoryRecord()
    {
        return $this->belongsTo(RoomMinibarInventory::class, 'inventory_record_id');
    }

    public function product()
    {
        return $this->belongsTo(MinibarProduct::class, 'product_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Calcular total automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->quantity && $model->unit_price) {
                $model->total = round($model->quantity * $model->unit_price, 2);
            }
        });
    }
}
