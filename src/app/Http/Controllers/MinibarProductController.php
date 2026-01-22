<?php

namespace App\Http\Controllers;

use App\Models\MinibarProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class MinibarProductController extends Controller
{
    /**
     * Listar productos
     */
    public function index(Request $request)
    {
        $query = MinibarProduct::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_sellable')) {
            $query->where('is_sellable', $request->boolean('is_sellable'));
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $products = $query->orderBy('name')->get();

        return response($products, Response::HTTP_OK);
    }

    /**
     * Obtener solo productos vendibles
     */
    public function getSellable()
    {
        $products = MinibarProduct::where('is_sellable', true)
            ->where('active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        return response($products, Response::HTTP_OK);
    }

    /**
     * Obtener solo productos no vendibles
     */
    public function getNonSellable()
    {
        $products = MinibarProduct::where('is_sellable', false)
            ->where('active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        return response($products, Response::HTTP_OK);
    }

    /**
     * Crear producto
     */
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:minibar_product_categories,id',
            'is_sellable' => 'boolean',
            'sale_price' => 'nullable|numeric|min:0',
            'unit' => 'required|string|max:50',
            'barcode' => 'nullable|string|max:125|unique:minibar_products,barcode',
            'image_url' => 'nullable|string|max:500',
            'stock_alert_threshold' => 'nullable|integer|min:0',
            'active' => 'boolean',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que productos vendibles tengan precio
        if ($request->boolean('is_sellable') && !$request->has('sale_price')) {
            return response()->json([
                'message' => 'Los productos vendibles deben tener un precio de venta'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $product = MinibarProduct::create($request->all());

        $product->load('category');

        return response($product, Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de producto
     */
    public function show(MinibarProduct $product)
    {
        $product->load('category');
        return response($product, Response::HTTP_OK);
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, MinibarProduct $product)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:250',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:minibar_product_categories,id',
            'is_sellable' => 'boolean',
            'sale_price' => 'nullable|numeric|min:0',
            'unit' => 'sometimes|required|string|max:50',
            'barcode' => 'nullable|string|max:125|unique:minibar_products,barcode,' . $product->id,
            'image_url' => 'nullable|string|max:500',
            'stock_alert_threshold' => 'nullable|integer|min:0',
            'active' => 'boolean',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que productos vendibles tengan precio
        if ($request->has('is_sellable') && $request->boolean('is_sellable') && !$request->has('sale_price') && !$product->sale_price) {
            return response()->json([
                'message' => 'Los productos vendibles deben tener un precio de venta'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $product->update($request->all());
        $product->load('category');

        return response($product, Response::HTTP_OK);
    }

    /**
     * Eliminar producto
     */
    public function destroy(MinibarProduct $product)
    {
        // Validar que no tenga stock o cargos asociados
        if ($product->roomStock()->count() > 0 || $product->charges()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene stock o cargos asociados'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $product->delete();

        return response()->json([
            'message' => 'Producto eliminado exitosamente'
        ], Response::HTTP_OK);
    }
}
