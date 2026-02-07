<?php

namespace App\Services;

use App\Models\EmployeeMeal;
use App\Models\EmployeeMealItem;
use App\Models\InventoryBatch;
use App\Models\InventoryConsumptionLog;
use App\Models\KitchenRecipe;
use App\Models\InventoryMeasureConversion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeMealService
{
    /**
     * Registrar comida de trabajador
     */
    public function registerMeal(
        int $userId,
        string $mealType,
        array $items,
        $date = null
    ): EmployeeMeal {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        DB::beginTransaction();
        try {
            // Crear registro de comida
            $employeeMeal = EmployeeMeal::create([
                'user_id' => $userId,
                'meal_type' => $mealType,
                'meal_date' => $date,
                'created_by' => auth()->id(),
            ]);

            $totalCost = 0;

            // Procesar cada item
            foreach ($items as $item) {
                $recipe = KitchenRecipe::with('recipeIngredients.inventoryInput')->find($item['recipe_id']);
                
                if (!$recipe) {
                    throw new \Exception("Receta no encontrada: {$item['recipe_id']}");
                }

                // Descontar inventario y calcular costo
                $itemCost = $this->consumeInventory($employeeMeal, $recipe, $item['quantity'], $item['measure_id']);
                $totalCost += $itemCost;
            }

            DB::commit();
            return $employeeMeal->fresh(['mealItems.recipe', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Descontar inventario de bodega para comida de trabajador y registrar en historial de consumo.
     */
    public function consumeInventory(
        EmployeeMeal $employeeMeal,
        KitchenRecipe $recipe,
        float $quantity,
        int $measureId
    ): float {
        // Crear el ítem de comida primero para poder asociar el log de consumo
        $mealItem = EmployeeMealItem::create([
            'employee_meal_id' => $employeeMeal->id,
            'recipe_id' => $recipe->id,
            'quantity' => $quantity,
            'measure_id' => $measureId,
            'inventory_cost' => 0,
        ]);

        $totalCost = 0;

        foreach ($recipe->recipeIngredients as $ingredient) {
            if (!$ingredient->inventoryInput) {
                throw new \Exception("La receta tiene un ingrediente sin producto de inventario asignado.");
            }

            $batches = InventoryBatch::with('input')
                ->whereDate('expiration_date', '>=', now())
                ->where('quantity', '>', 0)
                ->where('input_id', $ingredient->inventoryInput->id)
                ->orderBy('expiration_date', 'asc')
                ->get();

            if ($batches->count() === 0) {
                throw new \Exception("No hay inventario disponible para: {$ingredient->inventoryInput->name}");
            }

            $requiredQty = $this->calculateRequiredQuantity($ingredient, $batches->first(), $quantity);
            if ($requiredQty === false) {
                throw new \Exception("No se puede convertir la medida para: {$ingredient->inventoryInput->name}");
            }

            $totalInStock = $batches->sum('quantity');
            if ($requiredQty > $totalInStock) {
                throw new \Exception("Cantidad insuficiente de: {$ingredient->inventoryInput->name}. Requerido: {$requiredQty}, Disponible: {$totalInStock}");
            }

            $remainingToDiscount = $requiredQty;
            $itemCost = 0;

            foreach ($batches as $batch) {
                if ($remainingToDiscount <= 0) {
                    break;
                }

                $calculatedQty = $this->calculateRequiredQuantity($ingredient, $batch, $quantity);
                $toDeduct = 0;

                if ($calculatedQty <= $batch->quantity) {
                    $toDeduct = $calculatedQty;
                    $itemCost += $batch->price * $toDeduct;
                    $batch->quantity -= $toDeduct;
                    $remainingToDiscount -= $toDeduct;
                } else {
                    $toDeduct = $batch->quantity;
                    $itemCost += $batch->price * $toDeduct;
                    $remainingToDiscount -= $toDeduct;
                    $batch->quantity = 0;
                }

                $batch->save();

                if ($toDeduct > 0) {
                    InventoryConsumptionLog::create([
                        'order_item_id' => null,
                        'employee_meal_item_id' => $mealItem->id,
                        'inventory_batch_id' => $batch->id,
                        'input_id' => $ingredient->inventoryInput->id,
                        'quantity_consumed' => $toDeduct,
                        'measure_id' => $batch->input->measure_id ?? null,
                    ]);
                }
            }

            $totalCost += $itemCost;
        }

        $mealItem->update(['inventory_cost' => $totalCost]);

        return $totalCost;
    }

    /**
     * Calcular costo de inventario consumido
     */
    public function calculateInventoryCost(EmployeeMeal $employeeMeal): float
    {
        return $employeeMeal->getTotalCost();
    }

    /**
     * Obtener reporte de gastos de bodega por comidas de trabajadores
     */
    public function getEmployeeMealsReport($startDate, $endDate, $userId = null): array
    {
        $query = EmployeeMeal::with(['user', 'mealItems.recipe'])
            ->whereBetween('meal_date', [$startDate, $endDate]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $meals = $query->get();

        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_meals' => $meals->count(),
            'total_cost' => $meals->sum(function($meal) {
                return $meal->getTotalCost();
            }),
            'by_meal_type' => [
                'breakfast' => [
                    'count' => $meals->where('meal_type', 'breakfast')->count(),
                    'cost' => $meals->where('meal_type', 'breakfast')->sum(function($meal) {
                        return $meal->getTotalCost();
                    }),
                ],
                'lunch' => [
                    'count' => $meals->where('meal_type', 'lunch')->count(),
                    'cost' => $meals->where('meal_type', 'lunch')->sum(function($meal) {
                        return $meal->getTotalCost();
                    }),
                ],
                'dinner' => [
                    'count' => $meals->where('meal_type', 'dinner')->count(),
                    'cost' => $meals->where('meal_type', 'dinner')->sum(function($meal) {
                        return $meal->getTotalCost();
                    }),
                ],
            ],
            'by_employee' => $meals->groupBy('user_id')->map(function($userMeals) {
                return [
                    'employee' => $userMeals->first()->user->name,
                    'total_meals' => $userMeals->count(),
                    'total_cost' => $userMeals->sum(function($meal) {
                        return $meal->getTotalCost();
                    }),
                ];
            })->values(),
            'meals' => $meals,
        ];

        return $report;
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
