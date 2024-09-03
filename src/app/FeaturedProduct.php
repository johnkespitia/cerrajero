<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeaturedProduct extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id',
		'product_id',
		'order',
        'status'
    ];

    public function product()
    {
        return $this->belongsTo('App\Product','product_id');
    }

    public function category()
    {
        return $this->belongsTo('App\Category','category_id');
    }
}
