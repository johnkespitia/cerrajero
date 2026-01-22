<?php

namespace App\Http\Controllers;

use App\Models\EmployeeMeal;
use App\Models\User;
use App\Services\EmployeeMealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeMealController extends Controller
{
    protected $employeeMealService;

    public function __construct(EmployeeMealService $employeeMealService)
    {
        $this->employeeMealService = $employeeMealService;
    }

    /**
     * Listar comidas de trabajadores
     */
    public function index(Request $request)
    {
        $query = EmployeeMeal::with(['user', 'createdBy', 'mealItems.recipe', 'mealItems.measure']);

        // Filtros
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('meal_type')) {
            $query->where('meal_type', $request->meal_type);
        }

        if ($request->has('start_date')) {
            $query->where('meal_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('meal_date', '<=', $request->end_date);
        }

        $meals = $query->orderBy('meal_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Agregar costo total a cada comida
        $meals->each(function($meal) {
            $meal->total_cost = $meal->getTotalCost();
        });

        return response()->json($meals);
    }

    /**
     * Mostrar comida específica
     */
    public function show(EmployeeMeal $employeeMeal)
    {
        $employeeMeal->load(['user', 'createdBy', 'mealItems.recipe', 'mealItems.measure']);
        $employeeMeal->total_cost = $employeeMeal->getTotalCost();

        return response()->json($employeeMeal);
    }

    /**
     * Registrar comida de trabajador
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'meal_type' => 'required|in:breakfast,lunch,dinner',
            'meal_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.recipe_id' => 'required|exists:kitchen_recipes,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.measure_id' => 'required|exists:inventory_measures,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $employeeMeal = $this->employeeMealService->registerMeal(
                $request->user_id,
                $request->meal_type,
                $request->items,
                $request->meal_date
            );

            if ($request->has('notes')) {
                $employeeMeal->update(['notes' => $request->notes]);
            }

            return response()->json($employeeMeal, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la comida de trabajador',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar comida de trabajador (solo notas)
     */
    public function update(Request $request, EmployeeMeal $employeeMeal)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $employeeMeal->update($request->only('notes'));

        return response()->json($employeeMeal->load(['user', 'createdBy', 'mealItems.recipe']));
    }

    /**
     * Eliminar comida de trabajador
     */
    public function destroy(EmployeeMeal $employeeMeal)
    {
        // Nota: Al eliminar, los items se eliminan en cascada
        // Pero el inventario ya fue descontado, así que habría que revertirlo
        // Por ahora, solo permitimos eliminar si es muy reciente (menos de 1 hora)
        if ($employeeMeal->created_at->diffInHours(now()) > 1) {
            return response()->json([
                'message' => 'No se puede eliminar una comida registrada hace más de 1 hora'
            ], 422);
        }

        $employeeMeal->delete();

        return response()->json(null, 204);
    }

    /**
     * Obtener reporte de gastos de bodega
     */
    public function getReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $report = $this->employeeMealService->getEmployeeMealsReport(
            $request->start_date,
            $request->end_date,
            $request->user_id
        );

        return response()->json($report);
    }
}
