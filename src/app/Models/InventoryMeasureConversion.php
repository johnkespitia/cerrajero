<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class  InventoryMeasureConversion extends Model
{
    use HasFactory;
    protected $fillable = [
        'origin_id',
        'destination_id',
        'factor'
    ];
    public function originMeasure()
    {
        return $this->belongsTo(InventoryMeasure::class, 'origin_id');
    }

    public function destinationMeasure()
    {
        return $this->belongsTo(InventoryMeasure::class, 'destination_id');
    }
}
