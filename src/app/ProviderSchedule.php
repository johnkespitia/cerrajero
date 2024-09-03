<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProviderSchedule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'provider_id',
		'week_day',
		'start_hour',
		'end_hour',
		'close_day'
    ];
}
