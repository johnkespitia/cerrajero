<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProducedBatch extends Model
{
    use HasFactory;

    protected $fillable = ['order_item_id', 'quantity', 'expiration_date', 'batch_serial'];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class)   ;
    }

    public function packages()
    {
        return $this->hasMany(InventoryPackageConsume::class, "batch_id", "id");
    }
}
