<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    public function paymentMethod()
    {
        return $this->belongsTo('App\PaymentMethod','payment_method_id');
    }

}
