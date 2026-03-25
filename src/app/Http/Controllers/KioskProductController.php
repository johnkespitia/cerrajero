<?php

namespace App\Http\Controllers;

use App\Models\KioskProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class KioskProductController extends Controller
{
    /**
     * Listado de productos kiosko.
     *
     * Orden estable (spec kiosk-inventory-presentation): categoría (nombre) → producto (nombre) → id.
     * Query: include_inactive=1 incluye inactivos (p. ej. administración).
     */
    public function index(Request $request)
    {
        $query = KioskProduct::query()
            ->with(['category', 'tax'])
            ->leftJoin('kiosk_categories', 'kiosk_products.category_id', '=', 'kiosk_categories.id')
            ->orderBy('kiosk_categories.name')
            ->orderBy('kiosk_products.name')
            ->orderBy('kiosk_products.id')
            ->select('kiosk_products.*');

        if (!$request->boolean('include_inactive')) {
            $query->where('kiosk_products.active', true);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'code' => 'required|unique:kiosk_products',
            'sale_price' => 'required|min:0',
            'image' => 'nullable|image|max:2048', // Adjust validation rules for image uploads
            'description' => 'nullable',
            'active' => 'boolean',
            'category_id' => 'required|exists:kiosk_categories,id',
            'tax_id' => 'required|exists:taxes,id',
        ]);

        $kioskProduct = KioskProduct::create($request->all());
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products');
            $kioskProduct->update(['image' => $imagePath]);
        }
        return response()->json($kioskProduct, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(KioskProduct $kioskProduct)
    {
        return $kioskProduct;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskProduct $kioskProduct)
    {
        $request->validate([
            'name' => 'required',
            'code' => 'required|unique:kiosk_products,code,' . $kioskProduct->id,
            'image' => 'nullable|image|max:2048', // Adjust validation rules for image uploads
            'description' => 'nullable',
            'active' => 'boolean',
            'category_id' => 'required|exists:kiosk_categories,id',
            'tax_id' => 'required|exists:taxes,id',
            'sale_price' => 'required|min:0',
        ]);

        if ($request->hasFile('image')) {
            // Eliminar la imagen anterior si existe
            if ($kioskProduct->image) {
                Storage::delete($kioskProduct->image);
            }

            // Guardar la nueva imagen
            $imagePath = $request->file('image')->store('products');
            $request->merge(['image' => $imagePath]);
        }
        $kioskProduct->update($request->all());
        
        return response()->json($kioskProduct, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskProduct $kioskProduct)
    {
        $kioskProduct->delete();
        return response()->json(null, 204);
    }
}