<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinibarExpiredLog extends Model
{
    use HasFactory;

    protected $table = 'minibar_expired_log';

    protected $fillable = [
        'product_id',
        'quantity',
        'recorded_at',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(MinibarProduct::class, 'product_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
