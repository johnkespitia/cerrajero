<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinibarProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'is_sellable',
        'sale_price',
        'unit',
        'barcode',
        'image_url',
        'stock_alert_threshold',
        'active'
    ];

    protected $casts = [
        'is_sellable' => 'boolean',
        'sale_price' => 'decimal:2',
        'active' => 'boolean'
    ];

    public function category()
    {
        return $this->belongsTo(MinibarProductCategory::class, 'category_id');
    }

    public function roomStock()
    {
        return $this->hasMany(RoomMinibarStock::class, 'product_id');
    }

    public function inventoryRecords()
    {
        return $this->hasMany(RoomMinibarInventory::class, 'product_id');
    }

    public function charges()
    {
        return $this->hasMany(ReservationMinibarCharge::class, 'product_id');
    }

    public function restockingLogs()
    {
        return $this->hasMany(MinibarRestockingLog::class, 'product_id');
    }

    /**
     * Calcular stock actual basado en movimientos
     */
    public function getCurrentStockAttribute(): int
    {
        return $this->roomStock()->sum('current_quantity');
    }

    /**
     * Verificar si el stock está bajo el umbral de alerta
     */
    public function isStockLow(): bool
    {
        if (!$this->stock_alert_threshold) {
            return false;
        }
        return $this->current_stock < $this->stock_alert_threshold;
    }
}
