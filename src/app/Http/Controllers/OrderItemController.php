<?php

namespace App\Http\Controllers;

use App\Models\inventoryBatch;
use App\Models\inventoryMeasureConversion;
use App\Models\OrderItem;
use App\Models\ProducedBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderItemController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'recipe_id' => 'required|exists:kitchen_recipes,id',
            'quantity' => 'required|numeric|min:0',
            'measure_id' => 'required|exists:inventory_measures,id',
            'status' => 'required|in:pending, preparing, prepared, delivered',
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
            'measure_id' => 'sometimes|exists:inventory_measures,id',
            'status' => 'sometimes|in:pending,preparing,prepared,delivered',
        ]);
        if($this->updateInventory($orderItem)){
            $orderItem->update($validatedData);
            return response()->json($orderItem);
        }else{
            return response()->json(["error"=>"can't discount from the inventory"], 500);
        }
    }

    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();
        return response()->json(null, 204);
    }

    private function updateInventory(OrderItem $orderItem){
        if($orderItem->status === "prepared"){
            foreach ($orderItem->recipe->recipeIngredients as $ingredient){
                $batchs = inventoryBatch::whereDate('expiration_date', '>', now())->where("quantity",">",0)->where("input_id", $ingredient->inventoryInput->id)->get();
                $b = $batchs->first();
                $calculatedQuantity = $this->getConvertedQty($ingredient, $b);
                if(!$calculatedQuantity){
                    return false;
                }
                $totalInStock = $batchs->sum('quantity');
                if($calculatedQuantity > $totalInStock){
                    return false;
                }
                $discountedAll = 0;
                do{
                    if($calculatedQuantity <  $b->quantity){
                        $b->quantity = $b->quantity - $calculatedQuantity;
                        $discountedAll = $calculatedQuantity;
                        $b->save();
                    }else{
                        $b->quantity = 0;
                        $discountedAll += $calculatedQuantity - $discountedAll - $b->quantity;
                        $b->save();
                        $b = $batchs->next();
                    }
                }while($discountedAll < $calculatedQuantity);
            }
            $this->createProducedBatch($orderItem);
            return true;
        }
    }
    private function getConvertedQty($ingredient, $itemInventory){
        $convertedQuantity = 0;
        if($itemInventory->input->measure_id!=$ingredient->measure_id){
            $conversion = InventoryMeasureConversion::where('origin_id', $ingredient->measure_id)
                ->where('destination_id', $itemInventory->input->measure_id)
                ->orWhere(function ($query) use ($ingredient, $itemInventory) {
                    $query->where('destination_id', $ingredient->measure_id)
                        ->where('origin_id', $itemInventory->input->measure_id);
                })
                ->first();
            if (!$conversion) {
                return false;
            }
            if ($conversion->origin_id === $ingredient->measure_id) {
                $convertedQuantity = $ingredient->quantity * $conversion->factor;
            } else {
                $convertedQuantity = $ingredient->quantity / $conversion->factor;
            }
        }else{
            $convertedQuantity = $ingredient->quantity;
        }
        return $convertedQuantity;
    }

    private function createProducedBatch(OrderItem $orderItem){
        $uniqueID = Str::uuid()->toString();
        $itemBatch = ProducedBatch::create([
            "order_item_id"=> $orderItem->id,
            "quantity"=>$orderItem->quantity,
            "batch_serial"=>$uniqueID,
            "expiration_date"=>Carbon::today()->addMonth()
        ]);
        return $itemBatch;
    }
}
