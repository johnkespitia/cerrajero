<?php

namespace App\Http\Controllers;

use App\Models\InventoryConsumptionLog;
use Illuminate\Http\Request;

class InventoryConsumptionLogController extends Controller
{
    /**
     * Listar registros de consumo de materia prima (por órdenes/entregas).
     * Opcional: filtrar por input_id (producto de inventario).
     */
    public function index(Request $request)
    {
        $query = InventoryConsumptionLog::with([
            'orderItem.order',
            'orderItem.recipe',
            'employeeMealItem.employeeMeal.user',
            'employeeMealItem.recipe',
            'inventoryBatch',
            'input.measure',
            'measure',
        ])
            ->orderBy('created_at', 'desc');

        if ($request->filled('input_id')) {
            $query->where('input_id', $request->input_id);
        }

        $logs = $query->limit(200)->get();

        return response()->json($logs);
    }
}
