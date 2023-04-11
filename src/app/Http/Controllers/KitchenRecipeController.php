<?php

namespace App\Http\Controllers;

use App\Models\KitchenRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KitchenRecipeController extends Controller
{
    public function index()
    {
        $recipes = KitchenRecipe::with("inventoryMeasure")->with("recipeSteps")->with("recipeIngredients.inventoryInput")->with("recipeIngredients.inventoryMeasure")->get();
        return response()->json($recipes);
    }

    public function show(KitchenRecipe $recipe)
    {
        $recipe->recipeSteps;
        $recipe->recipeIngredients;
        return response()->json($recipe);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'yield' => 'required|numeric|min:0',
            'measure_id' => 'required|exists:inventory_measures,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $recipe = new KitchenRecipe();
        $recipe->name = $request->input('name');
        $recipe->description = $request->input('description');
        $recipe->yield = $request->input('yield');
        $recipe->measure_id = $request->input('measure_id');
        $recipe->save();

        return response()->json($recipe);
    }

    public function update(Request $request, KitchenRecipe $recipe)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'yield' => 'sometimes|numeric|min:0',
            'measure_id' => 'sometimes|exists:inventory_measures,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $recipe->name = $request->input('name')??$recipe->name;
        $recipe->description = $request->input('description')??$recipe->description;
        $recipe->yield = $request->input('yield')??$recipe->yield;
        $recipe->measure_id = $request->input('measure_id')??$recipe->measure_id;
        $recipe->save();

        return response()->json($recipe);
    }
}
