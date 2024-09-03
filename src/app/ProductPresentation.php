<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductPresentation extends Model
{
    protected $fillable = [
        'name_presentation',
		'internal_sku',
		'special_description',
		'product_id',
        'status'
    ];

    public function prices()
    {
        return $this->hasMany('App\ProductPresentationProviderPrice','product_presentation_id');
    }
    
    public function product()
    {
        return $this->belongsTo('App\Product');
    }
}
