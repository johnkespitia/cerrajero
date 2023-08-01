<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with("orderItems.recipe")
            ->with("orderItems.recipe.recipeIngredients.inventoryInput")
            ->with("orderItems.recipe.recipeIngredients.inventoryMeasure")
            ->with("orderItems.batchs")
            ->with("orderItems.batchs.packages")
            ->with("orderItems.batchs.packages.package")
            ->with("orderItems.measure")
            ->with("orderItems.consumedInputs.recipeIngredient.inventoryInput")
            ->with("user")
            ->orderBy("id", "desc")
            ->get();

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->user;
        $order->orderItems;
        return response()->json($order);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $order = Order::create($request->all());

        return response()->json($order, 201);
    }

    public function update(Request $request, Order $order)
    {
        if($order->open != 1){
            return response()->json("Orden no puede ser modificada",500);
        }
        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $order->update($request->all());

        return response()->json($order);
    }

    public function destroy(Order $order)
    {
        if($order->open != 1){
            return response()->json("Orden no puede ser modificada",500);
        }
        $order->delete();

        return response()->json(null, 204);
    }
}
