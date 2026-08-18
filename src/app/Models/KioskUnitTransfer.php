<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskUnitTransfer extends Model
{
    use HasFactory;

    protected $table = 'kiosk_unit_transfers';

    protected $fillable = [
        'transfer_batch_id',
        'unit_id',
        'kiosk_product_id',
        'minibar_product_id',
        'expiration',
        'transferred_by',
        'notes',
        'transferred_at',
    ];

    protected $casts = [
        'expiration' => 'date',
        'transferred_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(KioskUnit::class, 'unit_id');
    }

    public function kioskProduct()
    {
        return $this->belongsTo(KioskProduct::class, 'kiosk_product_id');
    }

    public function minibarProduct()
    {
        return $this->belongsTo(MinibarProduct::class, 'minibar_product_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
