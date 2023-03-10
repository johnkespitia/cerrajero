<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class inventoryTypeInput extends Model
{
    use HasFactory;
    protected $fillable = [
        'name'
    ];

    public function categories()
    {
        return $this->hasMany(inventoryCategory::class, "input_type_id", "id");
    }
}
