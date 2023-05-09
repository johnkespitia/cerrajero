<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMeasure extends Model
{
    protected $fillable = [
        'name'
    ];

    use HasFactory;

    public function conversionsOrigin()
    {
        return $this->hasMany(InventoryMeasureConversion::class, 'origin_id');
    }
}
