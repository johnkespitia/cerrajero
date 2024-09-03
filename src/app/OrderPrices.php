<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderPrices extends Model
{
    protected $fillable = [
        'product_price_id',
		'order_id',
		'price_status_id',
		'price',
        'price_provider',
        'tax',
        'quantity',
        'total_product',
        'comment_item'
    ];

    public function status()
    {
        return $this->belongsTo('App\ProductPriceStatus','price_status_id');
    }

    public function productPrice()
    {
        return $this->belongsTo('App\ProductPresentationProviderPrice','product_price_id');
    }

    public function shipping()
    {
        return $this->hasOne('App\OrderItemShipping', 'order_price_id', 'id');
    }
}
