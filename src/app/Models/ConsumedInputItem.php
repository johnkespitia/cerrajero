<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumedInputItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quantity',
        'description',
        'recipe_ingredient_id',
        'order_item_id'
    ];

    public function recipeIngredient()
    {
        return $this->belongsTo(KitchenRecipeIngredients::class, 'recipe_ingredient_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
