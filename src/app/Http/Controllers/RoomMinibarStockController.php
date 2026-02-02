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
     * Crear o actualizar stock de producto en habitación.
     * Valida que la suma en habitaciones no supere bodega; al asignar más se descuenta de bodega.
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

        $productId = (int) $request->product_id;
        $newQty = (int) $request->current_quantity;

        $existing = RoomMinibarStock::where('room_id', $room->id)
            ->where('product_id', $productId)
            ->first();
        $currentInThisRoom = $existing ? (int) $existing->current_quantity : 0;
        $totalInOtherRooms = $this->minibarService->getTotalInRoomsForProduct($productId) - $currentInThisRoom;

        try {
            $this->minibarService->ensureWarehouseAvailableForAssignment(
                $productId,
                $currentInThisRoom,
                $newQty,
                $totalInOtherRooms
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $delta = $newQty - $currentInThisRoom;
        if ($delta > 0) {
            $this->minibarService->deductFromWarehouse($productId, $delta);
        } elseif ($delta < 0) {
            $this->minibarService->addToWarehouse($productId, -$delta);
        }

        $stock = RoomMinibarStock::updateOrCreate(
            [
                'room_id' => $room->id,
                'product_id' => $productId,
            ],
            [
                'standard_quantity' => $request->standard_quantity,
                'current_quantity' => $newQty,
                'notes' => $request->notes,
                'active' => true,
            ]
        );

        $stock->load('product.category');

        return response($stock, Response::HTTP_OK);
    }

    /**
     * Actualizar stock. Valida que la suma en habitaciones no supere bodega; ajusta bodega al cambiar cantidad.
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

        $productId = (int) $stock->product_id;
        $newQty = $request->has('current_quantity') ? (int) $request->current_quantity : (int) $stock->current_quantity;
        $currentInThisRoom = (int) $stock->current_quantity;
        $totalInOtherRooms = $this->minibarService->getTotalInRoomsForProduct($productId) - $currentInThisRoom;

        try {
            $this->minibarService->ensureWarehouseAvailableForAssignment(
                $productId,
                $currentInThisRoom,
                $newQty,
                $totalInOtherRooms
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $delta = $newQty - $currentInThisRoom;
        if ($delta > 0) {
            $this->minibarService->deductFromWarehouse($productId, $delta);
        } elseif ($delta < 0) {
            $this->minibarService->addToWarehouse($productId, -$delta);
        }

        $stock->update($request->all());
        $stock->load('product.category');

        return response($stock, Response::HTTP_OK);
    }

    /**
     * Reponer productos (descuenta de bodega; no se puede reponer más de lo disponible)
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

        try {
            $logs = $this->minibarService->restockProducts(
                $room,
                $request->products,
                $request->reason ?? 'manual',
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
