<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'reservation_id',
        'external_reference',
        'price',
        'meal_type',
        'charge_to_room',
        'payment_type_id',
        'paid',
        'inventory_verified',
        'inventory_verification_date'
    ];

    protected $casts = [
        'charge_to_room' => 'boolean',
        'paid' => 'boolean',
        'inventory_verified' => 'boolean',
        'inventory_verification_date' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function mealConsumptions()
    {
        return $this->hasMany(ReservationMealConsumption::class);
    }

    public function orderPayments()
    {
        return $this->hasMany(OrderPayment::class);
    }
}
