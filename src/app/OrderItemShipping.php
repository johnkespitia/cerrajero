<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderItemShipping extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_price_id',
		'carrier_id',
		'traking_code',
        'price_shipping',
        'shipping_status',
        'guide_url',
        'quotation_id'
    ];
}
