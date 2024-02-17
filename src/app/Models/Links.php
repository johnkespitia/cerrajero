<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Links extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'link',
        'active',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'users');
    }
}
