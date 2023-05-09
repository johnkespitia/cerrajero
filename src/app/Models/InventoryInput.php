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
        'measure_id'
    ];

    public function category()
    {
        return $this->belongsTo(inventoryCategory::class, "category_id", "id");
    }

    public function measure()
    {
        return $this->belongsTo(inventoryMeasure::class, "measure_id", "id");
    }
}
