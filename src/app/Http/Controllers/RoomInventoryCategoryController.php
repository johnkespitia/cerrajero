<?php

namespace App\Http\Controllers;

use App\Models\RoomInventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomInventoryCategoryController extends Controller
{
    public function index()
    {
        $categories = RoomInventoryCategory::where('active', true)
            ->with('items')
            ->orderBy('name')
            ->get();
        return response($categories, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:125|unique:room_inventory_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = RoomInventoryCategory::create($request->all());

        return response(['message' => 'Categoría creada exitosamente', 'category' => $category], Response::HTTP_CREATED);
    }

    public function show(RoomInventoryCategory $roomInventoryCategory)
    {
        $roomInventoryCategory->load('items');
        return response($roomInventoryCategory, Response::HTTP_OK);
    }

    public function update(Request $request, RoomInventoryCategory $roomInventoryCategory)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:125|unique:room_inventory_categories,name,' . $roomInventoryCategory->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryCategory->update($request->all());

        return response(['message' => 'Categoría actualizada exitosamente', 'category' => $roomInventoryCategory], Response::HTTP_OK);
    }

    public function destroy(RoomInventoryCategory $roomInventoryCategory)
    {
        // Verificar si tiene artículos asociados
        if ($roomInventoryCategory->items()->count() > 0) {
            return response(['message' => 'No se puede eliminar la categoría porque tiene artículos asociados'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryCategory->delete();

        return response(['message' => 'Categoría eliminada exitosamente'], Response::HTTP_OK);
    }
}
