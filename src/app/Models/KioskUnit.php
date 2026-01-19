<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        "code_complement",
        "price",
        "expiration",
        "active",
        "product_id",
        'sold'
    ];

    protected $casts = [
        'active' => 'boolean',
        'sold' => 'boolean',
        'expiration' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(KioskProduct::class, 'product_id');
    }
}
