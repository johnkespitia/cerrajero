<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomInventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    public function items()
    {
        return $this->hasMany(RoomInventoryItem::class, 'category_id');
    }
}
