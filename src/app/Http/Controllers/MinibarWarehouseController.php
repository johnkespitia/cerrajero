<?php

namespace App\Http\Controllers;

use App\Models\MinibarExpiredLog;
use App\Models\MinibarProduct;
use App\Models\MinibarWarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MinibarWarehouseController extends Controller
{
    /**
     * Listar inventario de bodega: todos los productos con cantidad en bodega, precio costo y venta
     */
    public function index()
    {
        $products = MinibarProduct::where('active', true)
            ->with(['category', 'warehouseStock'])
            ->orderBy('name')
            ->get();

        $list = $products->map(function ($product) {
            $ws = $product->warehouseStock;
            $qty = $ws ? $ws->current_quantity : 0;
            $cost = $product->purchase_price ? (float) $product->purchase_price : null;
            $sale = $product->sale_price ? (float) $product->sale_price : null;
            return [
                'id' => $product->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'category' => $product->category ? $product->category->name : null,
                'current_quantity' => $qty,
                'purchase_price' => $cost,
                'sale_price' => $sale,
                'is_sellable' => $product->is_sellable,
                'unit' => $product->unit,
            ];
        });

        return response()->json($list, Response::HTTP_OK);
    }

    /**
     * Historial de productos reportados como vencidos (fecha, cantidad, usuario, producto)
     */
    public function expiredLog(Request $request)
    {
        $query = MinibarExpiredLog::with(['product.category', 'recordedBy'])
            ->orderBy('recorded_at', 'desc');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->has('date_from')) {
            $query->whereDate('recorded_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('recorded_at', '<=', $request->date_to);
        }
        if ($request->filled('recorded_by')) {
            $query->where('recorded_by', $request->recorded_by);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $items = $query->paginate($perPage);

        return response()->json($items, Response::HTTP_OK);
    }

    /**
     * Agregar unidades al inventario de bodega
     */
    public function addUnits(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'product_id' => 'required|exists:minibar_products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $productId = (int) $request->product_id;
        $quantity = (int) $request->quantity;

        DB::beginTransaction();
        try {
            $stock = MinibarWarehouseStock::firstOrCreate(
                ['product_id' => $productId],
                ['current_quantity' => 0]
            );
            $stock->current_quantity += $quantity;
            $stock->save();
            DB::commit();

            $stock->load('product.category');
            return response()->json([
                'message' => 'Unidades agregadas correctamente',
                'warehouse_stock' => $stock,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al agregar unidades',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Registrar unidades vencidas y descontarlas del total en bodega
     */
    public function registerExpired(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'product_id' => 'required|exists:minibar_products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $productId = (int) $request->product_id;
        $quantity = (int) $request->quantity;

        DB::beginTransaction();
        try {
            $stock = MinibarWarehouseStock::where('product_id', $productId)->first();
            if (!$stock || $stock->current_quantity < $quantity) {
                DB::rollBack();
                $available = $stock ? $stock->current_quantity : 0;
                return response()->json([
                    'message' => "No hay suficiente cantidad en bodega. Disponible: {$available}, solicitado: {$quantity}",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $stock->current_quantity -= $quantity;
            $stock->save();

            MinibarExpiredLog::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'recorded_at' => now(),
                'recorded_by' => auth()->id(),
            ]);

            DB::commit();

            $stock->load('product.category');
            return response()->json([
                'message' => 'Unidades vencidas registradas y descontadas correctamente',
                'warehouse_stock' => $stock,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar vencidas',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
