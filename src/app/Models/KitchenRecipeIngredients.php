<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KitchenRecipeIngredients extends Model
{
    protected $fillable = [
        'recipe_id',
        'input_id',
        'quantity',
        'measure_id'
    ];

    use HasFactory;

    public function recipe()
    {
        return $this->belongsTo(KitchenRecipe::class, "recipe_id","id");
    }

    public function inventoryMeasure()
    {
        return $this->belongsTo(InventoryMeasure::class, "measure_id","id");
    }

    public function inventoryInput()
    {
        return $this->belongsTo(InventoryInput::class, "input_id", "id");
    }
}
