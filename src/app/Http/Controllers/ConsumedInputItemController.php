<?php

namespace App\Http\Controllers;

use App\Models\ConsumedInputItem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsumedInputItemController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'items.*.recipe_ingredient_id' => 'required|exists:kitchen_recipe_ingredients,id',
                'items.*.description' => 'sometimes',
                'items.*.quantity' => 'required|numeric|min:0',
            ]);

            $orderItemId = $request->input('order_item_id');

            $createdItems = [];

            foreach ($validatedData["items"] as $itemData) {
                $itemData['order_item_id'] = $orderItemId;
                $createdItem = ConsumedInputItem::create($itemData);
                $createdItems[] = $createdItem;
            }

            return response()->json($createdItems, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422); // Código de estado 422 (Unprocessable Entity)
        }
    }

    public function update(Request $request,ConsumedInputItem $consumedInputItem)
    {
        $validatedData = $request->validate([
            'recipe_ingredient_id' => 'sometimes|exists:kitchen_recipe_ingredients,id',
            'order_item_id' => 'sometimes|exists:order_items,id',
            'description' => 'sometimes',
            'quantity' => 'sometimes|numeric|min:0',
        ]);

        $consumedInputItem->update($validatedData);
        return response()->json($consumedInputItem, 200);
    }
}
