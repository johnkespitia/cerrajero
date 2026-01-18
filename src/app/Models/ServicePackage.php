<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'room_type_id',
        'status',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function additionalServices()
    {
        return $this->belongsToMany(AdditionalService::class, 'service_package_services')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
