<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url_image',
		'title',
		'status',
		'product_id'
    ];
    
    public function product()
    {
        return $this->belongsTo('App\Product','product_id');
    }
}
