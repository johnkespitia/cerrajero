<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'latitude',
        'longitude',
        'arrival_directions',
        'address_remarks' ,
        'customer_id',
        'city_id',
        'address_type',
        'address',
        'phone',
        'state',
        'is_principal'
    ];

    public function Customer()
    {
        return $this->belongsTo('App\Customer', 'customer_id');
    }

    public function city()
    {
        return $this->belongsTo('App\City', 'city_id');
    }


}
