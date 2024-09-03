<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductPresentationProviderPrice extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'price_provider',
		'price',
		'status',
		'product_presentation_id',
        'provider_id',
        'qty',
        'external_url',
        'box_width',
        'box_height',
        'box_length',
        'box_weight',
        'price_full'
    ];

    public function provider()
    {
        return $this->belongsTo('App\Provider','provider_id');
    }
    
    public function presentation()
    {
        return $this->belongsTo('App\ProductPresentation','product_presentation_id');
    }
}
