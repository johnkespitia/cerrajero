<?php

namespace App\Http\Controllers;

use App\Models\MinibarProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class MinibarProductCategoryController extends Controller
{
    /**
     * Listar categorías
     */
    public function index(Request $request)
    {
        $query = MinibarProductCategory::query();

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $categories = $query->withCount('products')->orderBy('name')->get();

        return response($categories, Response::HTTP_OK);
    }

    /**
     * Crear categoría
     */
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:125',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'active' => 'boolean',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = MinibarProductCategory::create($request->all());

        return response($category, Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de categoría
     */
    public function show(MinibarProductCategory $category)
    {
        $category->load('products');
        return response($category, Response::HTTP_OK);
    }

    /**
     * Actualizar categoría
     */
    public function update(Request $request, MinibarProductCategory $category)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:125',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'active' => 'boolean',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->update($request->all());

        return response($category, Response::HTTP_OK);
    }

    /**
     * Eliminar categoría
     */
    public function destroy(MinibarProductCategory $category)
    {
        // Validar que no tenga productos asociados
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente'
        ], Response::HTTP_OK);
    }
}
