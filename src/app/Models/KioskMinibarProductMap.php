<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskMinibarProductMap extends Model
{
    use HasFactory;

    protected $table = 'kiosk_minibar_product_map';

    protected $fillable = [
        'kiosk_product_id',
        'minibar_product_id',
        'match_source',
    ];

    protected $casts = [
        'match_source' => 'string',
    ];

    public function kioskProduct()
    {
        return $this->belongsTo(KioskProduct::class, 'kiosk_product_id');
    }

    public function minibarProduct()
    {
        return $this->belongsTo(MinibarProduct::class, 'minibar_product_id');
    }
}
