<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    public function messages()
    {
        return $this->hasMany("App\Message" , "ticket_id");
    }

    public function client()
    {
        return $this->belongsTo('App\User','client_id');
    }

    public function order()
    {
        return $this->belongsTo('App\Order','order_id');
    }
}
