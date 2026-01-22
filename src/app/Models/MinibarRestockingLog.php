<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinibarRestockingLog extends Model
{
    use HasFactory;

    protected $table = 'minibar_restocking_log';

    protected $fillable = [
        'room_id',
        'product_id',
        'quantity_added',
        'quantity_before',
        'quantity_after',
        'restocked_at',
        'restocked_by',
        'reason',
        'notes'
    ];

    protected $casts = [
        'quantity_added' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'restocked_at' => 'datetime'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function product()
    {
        return $this->belongsTo(MinibarProduct::class, 'product_id');
    }

    public function restockedBy()
    {
        return $this->belongsTo(User::class, 'restocked_by');
    }
}
