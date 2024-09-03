<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'status_id',
        'total',
        'address_id',
    ];

    public function history()
    {
        return $this->hasMany('App\OrderHistory', 'order_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany('App\OrderPayment', 'order_id', 'id');
    }

    public function status()
    {
        return $this->belongsTo('App\OrderStatus','status_id');
    }

    public function address()
    {
        return $this->belongsTo('App\Address','address_id');
    }

    public function items()
    {
        return $this->hasMany('App\OrderPrices', 'order_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo('App\Customer', 'customer_id');
    }

    public function ticket()
    {
        return $this->HasOne('App\Ticket', 'order_id');
    }
}

