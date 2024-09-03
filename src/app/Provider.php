<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\ProviderType;
class Provider extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
		'nit',
		'email',
		'address',
		'location',
		'description',
        'provider_type_id',
        'phone',
        'image_provider',
        'city_id',
        'status',
        'general_percentage',
    ];

    public function provider_type()
    {
        return $this->belongsTo("App\ProviderType");
    }

    public function city()
    {
        return $this->belongsTo("App\City");
    }

    public function productsPrice()
    {
        return $this->hasMany("App\ProductPresentationProviderPrice", "provider_id");
    }

    public function schedule()
    {
        return $this->hasMany("App\ProviderSchedule", "provider_id");
    }
}
