<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenRecipeSteps extends Model
{
    protected $fillable = [
        'recipe_id',
        'description'
    ];

    use HasFactory;
    public function recipe()
    {
        return $this->belongsTo(KitchenRecipe::class);
    }
}
