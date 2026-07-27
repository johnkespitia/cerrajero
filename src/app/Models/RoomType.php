<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'image_url',
        'gallery',
        'default_capacity',
        'max_capacity',
        'base_price',
        'features',
        'active'
    ];

    protected $casts = [
        'gallery' => 'array',
        'features' => 'array',
        'base_price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function seasons()
    {
        return $this->hasMany(RoomSeason::class);
    }

    public function getMinGuestCapacity(): int
    {
        return max(1, (int) $this->default_capacity);
    }

    public function getMaxGuestCapacity(): int
    {
        $max = (int) ($this->max_capacity ?? $this->default_capacity);

        return max($this->getMinGuestCapacity(), $max);
    }

    public function guestCapacityViolationMessage(int $adults, int $children = 0): ?string
    {
        $totalGuests = $adults + $children;
        $min = $this->getMinGuestCapacity();
        $max = $this->getMaxGuestCapacity();

        if ($totalGuests < $min) {
            return "El tipo de habitación {$this->name} requiere al menos {$min} huésped(es) (adultos + niños).";
        }

        if ($totalGuests > $max) {
            return "El tipo de habitación {$this->name} admite máximo {$max} huésped(es) (adultos + niños).";
        }

        return null;
    }
}
