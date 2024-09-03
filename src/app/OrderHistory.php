<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    protected $fillable = [
        'order_id',
        'order_statuses_id',
    ];
    public function order()
    {
        return $this->belongsTo('App\Order','order_id');
    }
}
