<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class inventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'active',
        'serial',
        'input_id',
        'expiration_date',
        'quantity',
        'brand',
        'price'
    ];

    public function input()
    {
        return $this->belongsTo(inventoryInput::class, "input_id", "id");
    }
}
