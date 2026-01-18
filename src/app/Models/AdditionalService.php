<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'billing_type',
        'applies_to',
        'is_per_guest',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_per_guest' => 'boolean',
    ];

    public function reservationAdditionalServices()
    {
        return $this->hasMany(ReservationAdditionalService::class);
    }

    public function servicePackages()
    {
        return $this->belongsToMany(ServicePackage::class, 'service_package_services')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForReservationType($query, string $type)
    {
        if ($type === 'room') {
            return $query->whereIn('applies_to', ['room', 'both']);
        }
        if ($type === 'day_pass') {
            return $query->whereIn('applies_to', ['day_pass', 'both']);
        }
        return $query;
    }
}
