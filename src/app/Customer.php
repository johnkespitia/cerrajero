<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'document',
        'nombre',
        'apellido',
        'genre',
        'state',
        'user_id',
        'tipo_doc_id',
    ];
    public function User()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function addresses(){
        return $this->hasMany('App\Address' , 'customer_id');
    }

    public function tipo_documento(){
        return $this->belongsTo('App\TipoDocumento' , 'tipo_doc_id');
    }



}
