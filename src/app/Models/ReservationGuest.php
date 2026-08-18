<?php

namespace App\Models;

use App\Services\GuestAgeClassifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'birth_date',
        'gender',
        'nationality',
        'email',
        'phone',
        'special_needs',
        'is_primary_guest',
        'is_infant',
        'is_child',
        // Información de Seguro Social (EPS/Aseguradora)
        'health_insurance_name',
        'health_insurance_type'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_primary_guest' => 'boolean',
        'is_infant' => 'boolean',
        'is_child' => 'boolean',
    ];

    protected $appends = [
        'age_category',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeCategoryAttribute(): string
    {
        return app(GuestAgeClassifier::class)->categoryFromFlags(
            (bool) $this->is_infant,
            (bool) $this->is_child
        );
    }
}
