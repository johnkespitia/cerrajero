<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPackageConsume extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_consumed',
        'spoilt',
        'consumed_date',
        'package_id',
        'batch_id'
    ];

    public function package()
    {
        return $this->belongsTo(InventoryPackage::class, 'package_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProducedBatch::class, 'batch_id');
    }
}
