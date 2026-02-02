<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinibarWarehouseStock extends Model
{
    use HasFactory;

    protected $table = 'minibar_warehouse_stock';

    protected $fillable = [
        'product_id',
        'current_quantity',
    ];

    protected $casts = [
        'current_quantity' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(MinibarProduct::class, 'product_id');
    }
}
