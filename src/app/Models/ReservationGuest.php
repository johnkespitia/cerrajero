<?php

namespace App\Models;

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
        // Información de Seguro Social (EPS/Aseguradora)
        'health_insurance_name',
        'health_insurance_type'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_primary_guest' => 'boolean',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
