<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Listar proveedores
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Filtros
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name')->get();

        return response($suppliers, Response::HTTP_OK);
    }

    /**
     * Crear proveedor
     */
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'contact_name' => 'nullable|string|max:125',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:50',
            'service_type' => 'nullable|string|max:100',
            'rating' => 'nullable|numeric|min:1|max:5',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $supplier = Supplier::create($request->all());

        return response([
            'message' => 'Proveedor creado exitosamente',
            'supplier' => $supplier
        ], Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de proveedor
     */
    public function show(Supplier $supplier)
    {
        $supplier->load('maintenanceWorks');
        return response($supplier, Response::HTTP_OK);
    }

    /**
     * Actualizar proveedor
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:250',
            'contact_name' => 'nullable|string|max:125',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:50',
            'service_type' => 'nullable|string|max:100',
            'rating' => 'nullable|numeric|min:1|max:5',
            'notes' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $supplier->update($request->all());

        return response([
            'message' => 'Proveedor actualizado exitosamente',
            'supplier' => $supplier
        ], Response::HTTP_OK);
    }

    /**
     * Eliminar proveedor
     */
    public function destroy(Supplier $supplier)
    {
        // Validar que no tenga trabajos de mantenimiento
        if ($supplier->maintenanceWorks()->count() > 0) {
            return response([
                'message' => 'No se puede eliminar el proveedor porque tiene trabajos de mantenimiento asociados'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $supplier->delete();

        return response([
            'message' => 'Proveedor eliminado exitosamente'
        ], Response::HTTP_OK);
    }
}
