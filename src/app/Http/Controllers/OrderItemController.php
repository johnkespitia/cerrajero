<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'recipe_id' => 'required|exists:kitchen_recipes,id',
            'quantity' => 'required|numeric|min:0',
            'measure_id' => 'required|exists:inventory_measures,id'
        ]);

        $orderItem = OrderItem::create($validatedData);
        return response()->json($orderItem, 201);
    }

    public function update(Request $request, OrderItem $orderItem)
    {
        $validatedData = $request->validate([
            'order_id' => 'sometimes|exists:orders,id',
            'recipe_id' => 'sometimes|exists:kitchen_recipes,id',
            'quantity' => 'sometimes|numeric|min:0',
            'measure_id' => 'sometimes|exists:inventory_measures,id'
        ]);

        $orderItem->update($validatedData);
        return response()->json($orderItem);
    }

    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();
        return response()->json(null, 204);
    }
}
