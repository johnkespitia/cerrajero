<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'reason',
        'closure_type',
        'created_by',
        'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'string',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }

    public static function overlap($startDate, $endDate): bool
    {
        return self::active()->overlapping($startDate, $endDate)->exists();
    }
}
