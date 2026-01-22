<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\KitchenRecipe;
use App\Models\InventoryBatch;
use App\Models\InventoryMeasureConversion;

class InventoryVerificationService
{
    /**
     * Verificar inventario disponible para una orden completa
     */
    public function verifyOrderInventory(Order $order): array
    {
        $issues = [];
        $allAvailable = true;

        foreach ($order->orderItems as $orderItem) {
            $verification = $this->verifyOrderItemInventory($orderItem);
            
            if (!$verification['available']) {
                $allAvailable = false;
                $issues[] = [
                    'order_item_id' => $orderItem->id,
                    'recipe' => $orderItem->recipe->name,
                    'quantity' => $orderItem->quantity,
                    'issues' => $verification['issues']
                ];
            }
        }

        return [
            'available' => $allAvailable,
            'issues' => $issues
        ];
    }

    /**
     * Verificar inventario disponible para un OrderItem específico
     */
    public function verifyOrderItemInventory(OrderItem $orderItem): array
    {
        $issues = [];
        $allAvailable = true;

        if (!$orderItem->recipe) {
            return [
                'available' => false,
                'issues' => ['Receta no encontrada']
            ];
        }

        foreach ($orderItem->recipe->recipeIngredients as $ingredient) {
            $verification = $this->checkIngredientAvailability(
                $ingredient,
                $orderItem->quantity
            );

            if (!$verification['available']) {
                $allAvailable = false;
                $issues[] = [
                    'ingredient' => $ingredient->inventoryInput->name,
                    'required' => $verification['required'],
                    'available' => $verification['available'],
                    'message' => $verification['message']
                ];
            }
        }

        return [
            'available' => $allAvailable,
            'issues' => $issues
        ];
    }

    /**
     * Verificar disponibilidad de un ingrediente específico
     */
    public function checkIngredientAvailability($ingredient, $recipeQuantity = 1): array
    {
        // Obtener batches disponibles (no vencidos y con cantidad > 0)
        $batches = InventoryBatch::with('input')
            ->whereDate('expiration_date', '>=', now())
            ->where('quantity', '>', 0)
            ->where('input_id', $ingredient->inventoryInput->id)
            ->orderBy('expiration_date', 'asc') // FIFO: primero los que vencen antes
            ->get();

        if ($batches->count() === 0) {
            return [
                'available' => false,
                'required' => $ingredient->quantity * $recipeQuantity,
                'available' => 0,
                'message' => 'No hay inventario disponible para este ingrediente'
            ];
        }

        $totalInStock = $batches->sum('quantity');
        
        // Calcular cantidad requerida (considerando conversión de medidas)
        $requiredQuantity = $this->calculateRequiredQuantity($ingredient, $batches->first(), $recipeQuantity);

        if ($requiredQuantity === false) {
            return [
                'available' => false,
                'required' => $ingredient->quantity * $recipeQuantity,
                'available' => $totalInStock,
                'message' => 'No se puede convertir la medida del ingrediente'
            ];
        }

        if ($requiredQuantity > $totalInStock) {
            return [
                'available' => false,
                'required' => $requiredQuantity,
                'available' => $totalInStock,
                'message' => "Cantidad insuficiente. Requerido: {$requiredQuantity}, Disponible: {$totalInStock}"
            ];
        }

        return [
            'available' => true,
            'required' => $requiredQuantity,
            'available' => $totalInStock,
            'message' => 'Inventario suficiente'
        ];
    }

    /**
     * Obtener inventario disponible para una receta
     */
    public function getAvailableInventory(KitchenRecipe $recipe, $quantity = 1): array
    {
        $ingredients = [];
        $allAvailable = true;

        foreach ($recipe->recipeIngredients as $ingredient) {
            $verification = $this->checkIngredientAvailability($ingredient, $quantity);
            
            $ingredients[] = [
                'ingredient_id' => $ingredient->inventoryInput->id,
                'ingredient_name' => $ingredient->inventoryInput->name,
                'required_quantity' => $ingredient->quantity * $quantity,
                'available_quantity' => $verification['available'],
                'available' => $verification['available'],
                'message' => $verification['message']
            ];

            if (!$verification['available']) {
                $allAvailable = false;
            }
        }

        return [
            'recipe_id' => $recipe->id,
            'recipe_name' => $recipe->name,
            'quantity' => $quantity,
            'all_available' => $allAvailable,
            'ingredients' => $ingredients
        ];
    }

    /**
     * Verificar disponibilidad antes de crear orden
     */
    public function checkInventoryBeforeCreate(array $orderItems): array
    {
        $errors = [];
        $allAvailable = true;

        foreach ($orderItems as $item) {
            if (!isset($item['recipe_id']) || !isset($item['quantity'])) {
                $errors[] = 'Item inválido: falta recipe_id o quantity';
                $allAvailable = false;
                continue;
            }

            $recipe = KitchenRecipe::with('recipeIngredients.inventoryInput')->find($item['recipe_id']);
            
            if (!$recipe) {
                $errors[] = "Receta no encontrada: {$item['recipe_id']}";
                $allAvailable = false;
                continue;
            }

            $verification = $this->getAvailableInventory($recipe, $item['quantity']);
            
            if (!$verification['all_available']) {
                $allAvailable = false;
                $errors[] = [
                    'recipe' => $recipe->name,
                    'quantity' => $item['quantity'],
                    'ingredients' => array_filter($verification['ingredients'], function($ing) {
                        return !$ing['available'];
                    })
                ];
            }
        }

        return [
            'available' => $allAvailable,
            'errors' => $errors
        ];
    }

    /**
     * Calcular cantidad requerida considerando conversión de medidas
     */
    private function calculateRequiredQuantity($ingredient, $batch, $recipeQuantity = 1): float|false
    {
        $requiredQty = $ingredient->quantity * $recipeQuantity;

        // Si las medidas son diferentes, convertir
        if ($ingredient->measure_id != $batch->input->measure_id) {
            $conversion = InventoryMeasureConversion::where('origin_id', $ingredient->measure_id)
                ->where('destination_id', $batch->input->measure_id)
                ->orWhere(function ($query) use ($ingredient, $batch) {
                    $query->where('destination_id', $ingredient->measure_id)
                        ->where('origin_id', $batch->input->measure_id);
                })
                ->first();

            if (!$conversion) {
                return false;
            }

            if ($conversion->origin_id === $ingredient->measure_id) {
                return $requiredQty * $conversion->factor;
            } else {
                return $requiredQty / $conversion->factor;
            }
        }

        return $requiredQty;
    }
}
