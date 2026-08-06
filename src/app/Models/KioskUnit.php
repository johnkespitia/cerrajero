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
        'sold',
        'transferred_at'
    ];

    protected $casts = [
        'active' => 'boolean',
        'sold' => 'boolean',
        'expiration' => 'date',
        'transferred_at' => 'datetime',
    ];

    public function scopeAvailable($query)
    {
        return $query->where('active', true)
            ->where('sold', false)
            ->whereNull('transferred_at')
            ->where(function ($q) {
                $q->whereNull('expiration')
                    ->orWhere('expiration', '>=', now()->toDateString());
            });
    }

    public function product()
    {
        return $this->belongsTo(KioskProduct::class, 'product_id');
    }
}
