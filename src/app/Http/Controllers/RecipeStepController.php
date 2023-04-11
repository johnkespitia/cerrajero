<?php

namespace App\Http\Controllers;

use App\Models\KitchenRecipeSteps;
use Illuminate\Http\Request;

class RecipeStepController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'recipe_id' => 'required|exists:kitchen_recipes,id',
            'description' => 'required'
        ]);

        $recipeStep = KitchenRecipeSteps::create($validatedData);
        return response()->json($recipeStep, 201);
    }

    public function update(Request $request,KitchenRecipeSteps $recipeStep)
    {
        $validatedData = $request->validate([
            'recipe_id' => 'sometimes|exists:kitchen_recipes,id',
            'description' => 'sometimes'
        ]);

        $recipeStep->update($validatedData);
        return response()->json($recipeStep, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  KitchenRecipeSteps $recipeStep
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(KitchenRecipeSteps $recipeStep)
    {
        $recipeStep->delete();
        return response()->json(null, 204);
    }
}
