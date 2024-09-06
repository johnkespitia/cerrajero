<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'active'
    ];

    public function products()
    {
        return $this->hasMany(KioskProduct::class, "category_id");
    }
}
