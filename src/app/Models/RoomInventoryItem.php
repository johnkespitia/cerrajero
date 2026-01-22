<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'brand',
        'model',
        'serial_number',
        'barcode',
        'purchase_price',
        'current_value',
        'purchase_date',
        'warranty_expires_at',
        'image_url',
        'active'
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'purchase_date' => 'date',
        'warranty_expires_at' => 'date',
        'active' => 'boolean'
    ];

    public function category()
    {
        return $this->belongsTo(RoomInventoryCategory::class, 'category_id');
    }

    public function assignments()
    {
        return $this->hasMany(RoomInventoryAssignment::class, 'item_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(RoomInventoryAssignment::class, 'item_id')
                    ->where('active', true);
    }

    public function history()
    {
        return $this->hasMany(RoomInventoryHistory::class, 'item_id');
    }
}
