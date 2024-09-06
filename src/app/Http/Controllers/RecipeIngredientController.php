<?php

namespace App\Http\Controllers;

use App\Models\KitchenRecipeIngredients;
use Illuminate\Http\Request;

class RecipeIngredientController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'recipe_id' => 'required|exists:kitchen_recipes,id',
            'measure_id' => 'required|exists:inventory_measures,id',
            'input_id' => 'required|exists:inventory_inputs,id',
            'quantity' => 'required|numeric|min:0',
        ]);

        $recipeIngredient = KitchenRecipeIngredients::create($validatedData);

        return response()->json([
            'message' => 'Recipe ingredient created successfully',
            'recipe_ingredient' => $recipeIngredient,
        ]);
    }

    public function update(Request $request, KitchenRecipeIngredients $recipeIngredient)
    {
        $validatedData = $request->validate([
            'recipe_id' => 'sometimes|exists:kitchen_recipes,id',
            'measure_id' => 'sometimes|exists:inventory_measures,id',
            'input_id' => 'sometimes|exists:inventory_inputs,id',
            'quantity' => 'sometimes|numeric|min:0',
        ]);

        $recipeIngredient->update($validatedData);

        return response()->json([
            'message' => 'Recipe ingredient updated successfully',
            'recipe_ingredient' => $recipeIngredient,
        ]);
    }


    public function destroy(KitchenRecipeIngredients $recipeIngredient)
    {
        $recipeIngredient->delete();

        return response()->json([
            'message' => 'Recipe ingredient deleted successfully',
        ]);
    }
}
