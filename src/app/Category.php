<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
		'description',
		'category_parent_id',
        'status',
        'image',
        'icon',
        'category_type'
    ];

    public function parent()
    {
        return $this->belongsTo('App\Category','category_parent_id');
    }

    public function children()
    {
        return $this->hasMany('App\Category', 'category_parent_id', 'id');
    }

    public function featured_products()
    {
        return $this->hasMany('App\FeaturedProduct', 'category_id', 'id');
    }
}
