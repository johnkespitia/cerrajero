<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cupon extends Model
{

    const TYPE_PERCENTAGE = "percentage";
    const TYPE_PRICE = "price";

    protected $fillable = [
        'value',
        'active',
        'type',
        'expiration_date',
        'name',
        'uses',
    ];
}
