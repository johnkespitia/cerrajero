<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'recipe_id',
        'quantity',
        'measure_id',
        'status',
        'package_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function recipe()
    {
        return $this->belongsTo(KitchenRecipe::class);
    }

    public function measure()
    {
        return $this->belongsTo(InventoryMeasure::class);
    }
    public function batchs()
    {
        return $this->HasMany(ProducedBatch::class);
    }
}
