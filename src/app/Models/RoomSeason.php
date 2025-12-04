<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RoomSeason extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'name',
        'start_date',
        'end_date',
        'price_multiplier',
        'fixed_price',
        'active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price_multiplier' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function isActiveForDate($date)
    {
        if (!$this->active) {
            return false;
        }

        $checkDate = Carbon::parse($date);
        return $checkDate->between($this->start_date, $this->end_date);
    }

    public static function getSeasonForDate($roomTypeId, $date)
    {
        return self::where('room_type_id', $roomTypeId)
            ->where('active', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}

