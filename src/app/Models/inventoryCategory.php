<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class inventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'input_type_id'
    ];

    public function inputTypes()
    {
        return $this->belongsTo(inventoryTypeInput::class, "input_type_id", "id");
    }
}
