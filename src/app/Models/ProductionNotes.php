<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionNotes extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'user_id',
        'note',
    ];

    public function order_item()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
