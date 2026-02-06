<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'yield',
        'measure_id',
        'default_price',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
    ];

    public function recipeIngredients()
    {
        return $this->hasMany(KitchenRecipeIngredients::class, "recipe_id","id");
    }

    public function recipeSteps()
    {
        return $this->hasMany(KitchenRecipeSteps::class, "recipe_id","id");
    }

    public function inventoryMeasure()
    {
        return $this->belongsTo(InventoryMeasure::class, "measure_id","id");
    }
}
