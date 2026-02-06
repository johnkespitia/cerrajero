<?php

namespace App\Http\Controllers;

use App\Mail\InventoryLimitEmail;
use App\Models\InventoryBatch;
use App\Models\InventoryConsumptionLog;
use App\Models\InventoryMeasureConversion;
use App\Models\OrderItem;
use App\Models\ProducedBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            'unit_price' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending, preparing, prepared, delivered',
        ]);

        $recipe = \App\Models\KitchenRecipe::find($validatedData['recipe_id']);
        if (empty($validatedData['unit_price']) && ($recipe->default_price ?? null) !== null) {
            $validatedData['unit_price'] = $recipe->default_price;
        }

        // Validar inventario antes de crear el item
        $orderItem = new OrderItem($validatedData);
        $orderItem->load('recipe.recipeIngredients.inventoryInput');
        
        $inventoryVerificationService = app(\App\Services\InventoryVerificationService::class);
        $verification = $inventoryVerificationService->verifyOrderItemInventory($orderItem);
        
        if (!$verification['available']) {
            return response()->json([
                'error' => 'No hay suficiente inventario para esta receta',
                'issues' => $verification['issues']
            ], 422);
        }

        $orderItem = OrderItem::create($validatedData);
        return response()->json($orderItem, 201);
    }

    /**
     * Verificar inventario disponible para una receta
     */
    public function checkInventory(Request $request)
    {
        $request->validate([
            'recipe_id' => 'required|exists:kitchen_recipes,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $recipe = \App\Models\KitchenRecipe::with('recipeIngredients.inventoryInput')->findOrFail($request->recipe_id);
        
        $inventoryVerificationService = app(\App\Services\InventoryVerificationService::class);
        $verification = $inventoryVerificationService->getAvailableInventory($recipe, $request->quantity);

        return response()->json($verification);
    }

    public function update(Request $request, OrderItem $orderItem)
    {
        $validatedData = $request->validate([
            'order_id' => 'sometimes|exists:orders,id',
            'recipe_id' => 'sometimes|exists:kitchen_recipes,id',
            'quantity' => 'sometimes|numeric|min:0',
            'measure_id' => 'sometimes|exists:inventory_measures,id',
            'unit_price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,preparing,prepared,delivered',
        ]);
        $newStatus = $validatedData['status'] ?? $orderItem->status;
        if ($newStatus === 'preparing') {
            $orderItem->load('recipe.recipeIngredients.inventoryInput.measure');
            $issues = $this->getIngredientBatchIssues($orderItem);
            if (!empty($issues)) {
                return response()->json([
                    'error' => 'No hay suficientes productos en el inventario para la receta',
                    'issues' => $issues,
                ], 500);
            }
        }
        if ($this->updateInventory($orderItem, $newStatus)) {
            $orderItem->update($validatedData);
            return response()->json($orderItem);
        }
        $orderItem->load('recipe.recipeIngredients.inventoryInput.measure');
        $issues = $this->getIngredientBatchIssues($orderItem);
        return response()->json([
            'error' => 'No hay suficientes productos en el inventario para la receta',
            'issues' => $issues,
        ], 500);
    }

    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();
        return response()->json(null, 204);
    }

    /**
     * Devuelve los ingredientes que no cumplen con el inventario (requerido vs disponible).
     */
    private function getIngredientBatchIssues(OrderItem $orderItem): array
    {
        $issues = [];
        $multiplier = max(1, (float) $orderItem->quantity);
        foreach ($orderItem->recipe->recipeIngredients as $ingredient) {
            $input = $ingredient->inventoryInput;
            if (!$input) {
                $issues[] = [
                    'product' => 'Ingrediente sin asignar',
                    'required' => null,
                    'available' => null,
                    'message' => 'Un ingrediente de la receta no tiene producto de inventario asignado.',
                ];
                continue;
            }
            $batchs = InventoryBatch::whereDate('expiration_date', '>=', now())
                ->where('quantity', '>', 0)
                ->where('input_id', $input->id)
                ->get();
            $totalInStock = $batchs->sum('quantity');
            $ingredientName = $input->name ?? 'Ingrediente';
            $measureName = $input->measure->name ?? 'un';

            if ($batchs->count() === 0) {
                $required = $ingredient->quantity * $multiplier;
                $issues[] = [
                    'product' => $ingredientName,
                    'required' => $required,
                    'available' => 0,
                    'message' => "{$ingredientName}: no hay inventario disponible (requerido: {$required} {$measureName})",
                ];
                continue;
            }
            $calculatedQuantity = $this->getConvertedQty($ingredient, $batchs->first());
            if ($calculatedQuantity === false) {
                $issues[] = [
                    'product' => $ingredientName,
                    'required' => null,
                    'available' => $totalInStock,
                    'message' => "{$ingredientName}: no se puede convertir la medida con el inventario",
                ];
                continue;
            }
            $required = $calculatedQuantity * $multiplier;
            if ($required > $totalInStock) {
                $issues[] = [
                    'product' => $ingredientName,
                    'required' => round($required, 2),
                    'available' => round($totalInStock, 2),
                    'message' => "{$ingredientName}: requerido {$required} {$measureName}, disponible {$totalInStock}",
                ];
            }
        }
        return $issues;
    }

    private function validateIngredientBatch(OrderItem $orderItem)
    {
        return empty($this->getIngredientBatchIssues($orderItem));
    }
    /**
     * En "preparing" solo se valida inventario; el descuento y registro se hacen al marcar "delivered".
     */
    private function updateInventory(OrderItem $orderItem, string $status): bool
    {
        if ($status === 'preparing') {
            return $this->validateIngredientBatch($orderItem);
        }
        if ($status === 'delivered') {
            if (InventoryConsumptionLog::where('order_item_id', $orderItem->id)->exists()) {
                return true;
            }
            if (!$this->validateIngredientBatch($orderItem)) {
                return false;
            }
            return $this->deductInventoryAndLog($orderItem);
        }
        return true;
    }

    /**
     * Descuenta materia prima del inventario por ítem (cantidad × platos, con conversión de medida)
     * y registra cada consumo para trazabilidad.
     */
    private function deductInventoryAndLog(OrderItem $orderItem): bool
    {
        $multiplier = max(1, (float) $orderItem->quantity);
        $orderItem->load('recipe.recipeIngredients.inventoryInput');

        foreach ($orderItem->recipe->recipeIngredients as $ingredient) {
            $input = $ingredient->inventoryInput;
            if (!$input) {
                return false;
            }
            $batchs = InventoryBatch::with('input')
                ->whereDate('expiration_date', '>=', now())
                ->where('quantity', '>', 0)
                ->where('input_id', $input->id)
                ->orderBy('expiration_date', 'asc')
                ->get();

            $totalInStock = $batchs->sum('quantity');
            $perUnit = $batchs->isEmpty() ? 0 : $this->getConvertedQty($ingredient, $batchs->first());
            if ($perUnit === false || $perUnit <= 0) {
                return false;
            }
            $totalNeeded = $perUnit * $multiplier;
            if ($totalNeeded > $totalInStock) {
                return false;
            }

            $remaining = $totalNeeded;
            foreach ($batchs as $batch) {
                if ($remaining <= 0) {
                    break;
                }
                $toDeduct = min((float) $batch->quantity, $remaining);
                if ($toDeduct <= 0) {
                    continue;
                }
                $newQty = (int) round(max(0, (float) $batch->quantity - $toDeduct));
                $batch->quantity = $newQty;
                $batch->save();

                InventoryConsumptionLog::create([
                    'order_item_id' => $orderItem->id,
                    'inventory_batch_id' => $batch->id,
                    'input_id' => $input->id,
                    'quantity_consumed' => $toDeduct,
                    'measure_id' => $batch->input->measure_id ?? null,
                ]);

                $remaining -= $toDeduct;

                $newTotalInStock = InventoryBatch::where('input_id', $input->id)
                    ->whereDate('expiration_date', '>=', now())
                    ->where('quantity', '>', 0)
                    ->sum('quantity');
                if ($newTotalInStock <= $input->min_inventory) {
                    Mail::to(env('MAIN_NOTIFICATION_EMAIL'))->send(new InventoryLimitEmail($input, $newTotalInStock));
                }
            }
        }

        $this->createProducedBatch($orderItem);
        return true;
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
