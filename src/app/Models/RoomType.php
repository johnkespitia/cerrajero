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
}
