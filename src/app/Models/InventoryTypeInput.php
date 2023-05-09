<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTypeInput extends Model
{
    use HasFactory;
    protected $fillable = [
        'name'
    ];

    public function categories()
    {
        return $this->hasMany(InventoryCategory::class, "input_type_id", "id");
    }
}
