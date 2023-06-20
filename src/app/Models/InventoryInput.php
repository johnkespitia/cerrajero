<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryInput extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'serial',
        'active',
        'category_id',
        'measure_id',
        'min_inventory'
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, "category_id", "id");
    }

    public function measure()
    {
        return $this->belongsTo(InventoryMeasure::class, "measure_id", "id");
    }
}
