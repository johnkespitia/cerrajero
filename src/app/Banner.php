<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url_image',
        'url_image_mov',
		'url_link',
		'title',
        'status',
        'order',
        'description'
    ];
}
