<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomMinibarStock;
use App\Services\MinibarInventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomMinibarStockController extends Controller
{
    protected $minibarService;

    public function __construct(MinibarInventoryService $minibarService)
    {
        $this->minibarService = $minibarService;
    }

    /**
     * Listar stock de una habitación
     */
    public function index(Room $room)
    {
        $stock = RoomMinibarStock::where('room_id', $room->id)
            ->where('active', true)
            ->with('product.category')
            ->get();

        return response($stock, Response::HTTP_OK);
    }

    /**
     * Crear o actualizar stock de producto en habitación
     */
    public function store(Request $request, Room $room)
    {
        $validation = Validator::make($request->all(), [
            'product_id' => 'required|exists:minibar_products,id',
            'standard_quantity' => 'required|integer|min:0',
            'current_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stock = RoomMinibarStock::updateOrCreate(
            [
                'room_id' => $room->id,
                'product_id' => $request->product_id,
            ],
            [
                'standard_quantity' => $request->standard_quantity,
                'current_quantity' => $request->current_quantity,
                'notes' => $request->notes,
                'active' => true,
            ]
        );

        $stock->load('product.category');

        return response($stock, Response::HTTP_OK);
    }

    /**
     * Actualizar stock
     */
    public function update(Request $request, Room $room, RoomMinibarStock $stock)
    {
        $validation = Validator::make($request->all(), [
            'standard_quantity' => 'sometimes|required|integer|min:0',
            'current_quantity' => 'sometimes|required|integer|min:0',
            'notes' => 'nullable|string',
            'active' => 'boolean',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stock->update($request->all());
        $stock->load('product.category');

        return response($stock, Response::HTTP_OK);
    }

    /**
     * Reponer productos
     */
    public function restock(Request $request, Room $room)
    {
        $validation = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*' => 'required|integer|min:1',
            'reason' => 'nullable|in:standard,after_checkout,low_stock,manual',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $logs = $this->minibarService->restockProducts(
            $room,
            $request->products,
            $request->reason ?? 'manual',
            auth()->id()
        );

        return response([
            'message' => 'Productos repuestos exitosamente',
            'logs' => $logs
        ], Response::HTTP_OK);
    }

    /**
     * Obtener productos que necesitan reposición
     */
    public function needingRestock(Room $room)
    {
        $products = $this->minibarService->getProductsNeedingRestock($room);

        return response($products, Response::HTTP_OK);
    }
}
