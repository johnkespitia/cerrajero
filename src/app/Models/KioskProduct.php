<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KioskProduct extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        "code",
        "image",
        "description",
        "active",
        "category_id"
    ];

    public function category()
    {
        return $this->belongsTo(KioskCategory::class, 'category_id');
    }
}
