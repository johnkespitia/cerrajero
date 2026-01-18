<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationAdditionalService extends Model
{
    use HasFactory;

    protected $table = 'reservation_additional_services';

    protected $fillable = [
        'reservation_id',
        'additional_service_id',
        'unit_price',
        'quantity',
        'guests_count',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function additionalService()
    {
        return $this->belongsTo(AdditionalService::class);
    }
}
