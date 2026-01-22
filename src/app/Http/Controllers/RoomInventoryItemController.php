<?php

namespace App\Http\Controllers;

use App\Models\RoomInventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomInventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomInventoryItem::with(['category', 'activeAssignments.assignable']);

        // Filtros
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('name')->get();

        return response($items, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:room_inventory_categories,id',
            'brand' => 'nullable|string|max:125',
            'model' => 'nullable|string|max:125',
            'serial_number' => 'nullable|string|max:125|unique:room_inventory_items,serial_number',
            'barcode' => 'nullable|string|max:125|unique:room_inventory_items,barcode',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expires_at' => 'nullable|date',
            'image_url' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = RoomInventoryItem::create($request->all());
        $item->load('category');

        return response(['message' => 'Artículo creado exitosamente', 'item' => $item], Response::HTTP_CREATED);
    }

    public function show(RoomInventoryItem $roomInventoryItem)
    {
        $roomInventoryItem->load(['category', 'assignments.assignable', 'history.user']);
        return response($roomInventoryItem, Response::HTTP_OK);
    }

    public function update(Request $request, RoomInventoryItem $roomInventoryItem)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:250',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:room_inventory_categories,id',
            'brand' => 'nullable|string|max:125',
            'model' => 'nullable|string|max:125',
            'serial_number' => 'nullable|string|max:125|unique:room_inventory_items,serial_number,' . $roomInventoryItem->id,
            'barcode' => 'nullable|string|max:125|unique:room_inventory_items,barcode,' . $roomInventoryItem->id,
            'purchase_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expires_at' => 'nullable|date',
            'image_url' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryItem->update($request->all());
        $roomInventoryItem->load('category');

        return response(['message' => 'Artículo actualizado exitosamente', 'item' => $roomInventoryItem], Response::HTTP_OK);
    }

    public function destroy(RoomInventoryItem $roomInventoryItem)
    {
        // Verificar si tiene asignaciones activas
        if ($roomInventoryItem->activeAssignments()->count() > 0) {
            return response(['message' => 'No se puede eliminar el artículo porque tiene asignaciones activas'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryItem->delete();

        return response(['message' => 'Artículo eliminado exitosamente'], Response::HTTP_OK);
    }
}
