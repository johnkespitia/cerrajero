<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
class Product extends Model
{
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
		'sku',
		'description',
		'category_id',
        'status'
    ];
    
    public function category()
    {
        return $this->belongsTo('App\Category','category_id');
    }

    public function images()
    {
        return $this->hasMany('App\ProductImage','product_id');
    }

    public function presentations()
    {
        return $this->hasMany('App\ProductPresentation','product_id');
        
    }

    public function attributes()
    {
        return $this->belongsToMany('App\Attribute')
        ->using('App\AttributeProduct')
        ->withPivot([
            'value'
        ]);
    }
}
