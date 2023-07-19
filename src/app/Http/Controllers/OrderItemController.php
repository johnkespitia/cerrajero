<?php

namespace App\Http\Controllers;

use App\Mail\InventoryLimitEmail;
use App\Models\inventoryBatch;
use App\Models\inventoryMeasureConversion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProducedBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            'status' => 'required|in:Pendiente,En Producción,Producto Sin Empaque,Producto Empacado,Despachado',
        ]);
        $order = Order::find($validatedData["order_id"]);
        if($order->open != 1){
            return response()->json("Orden no puede ser modificada",500);
        }
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
            'status' => 'sometimes|in:Pendiente,En Producción,Producto Sin Empaque,Producto Empacado,Despachado',
        ]);
        $order = Order::find($validatedData["order_id"]);
        if($order->open != 1){
            return response()->json("Orden no puede ser modificada",500);
        }
        if($this->updateInventory($orderItem, $validatedData['status']??"" )){
            $orderItem->update($validatedData);
            return response()->json($orderItem);
        }else{
            return response()->json(["error"=>"No hay suficientes productos en el inventario para la receta"], 500);
        }
    }

    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();
        return response()->json(null, 204);
    }

    private function validateIngredientBatch(OrderItem $orderItem){
        foreach ($orderItem->recipe->recipeIngredients as $ingredient) {
            $batchs = inventoryBatch::whereDate('expiration_date', '>=', now())->where("quantity", ">", 0)->where("input_id", $ingredient->inventoryInput->id)->get();
            Log::warning($batchs);
            $totalInStock = $batchs->sum('quantity');
            Log::warning($totalInStock);
            if ($batchs->count() === 0) {
                return false;
            }
            $calculatedQuantity = $this->getConvertedQty($ingredient, $batchs->first());
            Log::warning($calculatedQuantity);
            if(!$calculatedQuantity){
                return false;
            }
            if($calculatedQuantity > $totalInStock){
                return false;
            }
        }
        return true;
    }
    private function updateInventory(OrderItem $orderItem, string $status){
        if($status === "En Producción"){
            if(!$this->validateIngredientBatch($orderItem)){
                Log::error("No creado !!");
                Log::error($orderItem);
                return false;
            }
            foreach ($orderItem->recipe->recipeIngredients as $ingredient){
                $batchs = inventoryBatch::whereDate('expiration_date', '>=', now())->where("quantity",">",0)->where("input_id", $ingredient->inventoryInput->id)->get();
                $totalInStock = $batchs->sum('quantity');
                $discountedAll = 0;
                if($batchs->count() === 0){
                    return false;
                }
                foreach ($batchs as $batch) {
                    $calculatedQuantity = $this->getConvertedQty($ingredient, $batch);
                    if(!$calculatedQuantity){
                        return false;
                    }
                    if($calculatedQuantity > $totalInStock){
                        return false;
                    }
                    if($discountedAll < $calculatedQuantity){
                        if ($calculatedQuantity < $batch->quantity) {
                            $batch->quantity = $batch->quantity - $calculatedQuantity;
                            $discountedAll = $calculatedQuantity;
                            $batch->save();
                        } else {
                            $batch->quantity = 0;
                            $discountedAll += $calculatedQuantity - $discountedAll - $batch->quantity;
                            $batch->save();
                        }
                    }
                }
                $newTotalInStock = $batchs->sum('quantity');
                if($newTotalInStock <= $ingredient->inventoryInput->min_inventory){
                    Mail::to(env("MAIN_NOTIFICATION_EMAIL"))->send(new InventoryLimitEmail($ingredient->inventoryInput, $newTotalInStock));
                }
            }

            $this->createProducedBatch($orderItem);
            return true;
        }else{
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
                Log::error("No Conversion");
                return $convertedQuantity;
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
