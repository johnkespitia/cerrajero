<?php

namespace App\Http\Controllers;

use App\Models\CommonArea;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class CommonAreaController extends Controller
{
    public function index(Request $request)
    {
        $query = CommonArea::with('activeAssignments.item');

        // Filtros
        if ($request->has('area_type')) {
            $query->where('area_type', $request->area_type);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $commonAreas = $query->orderBy('name')->get();

        return response($commonAreas, Response::HTTP_OK);
    }

    /**
     * Listar zonas comunes básicas (solo para selectores, sin información sensible)
     * No requiere permiso específico, solo autenticación
     */
    public function listBasic(Request $request)
    {
        $query = CommonArea::select('id', 'name', 'code', 'active');

        // Filtro por activos
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $commonAreas = $query->orderBy('name')->get();
        return response($commonAreas, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:125',
            'code' => 'nullable|string|max:50|unique:common_areas,code',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:250',
            'area_type' => 'required|in:pool,garden,terrace,lounge,restaurant,gym,spa,other',
            'capacity' => 'nullable|integer|min:1',
            'image_url' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $commonArea = CommonArea::create($request->all());

        return response(['message' => 'Zona común creada exitosamente', 'common_area' => $commonArea], Response::HTTP_CREATED);
    }

    public function show(CommonArea $commonArea)
    {
        $commonArea->load(['activeAssignments.item', 'history.user']);
        return response($commonArea, Response::HTTP_OK);
    }

    public function update(Request $request, CommonArea $commonArea)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:125',
            'code' => 'nullable|string|max:50|unique:common_areas,code,' . $commonArea->id,
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:250',
            'area_type' => 'sometimes|in:pool,garden,terrace,lounge,restaurant,gym,spa,other',
            'capacity' => 'nullable|integer|min:1',
            'image_url' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $commonArea->update($request->all());

        return response(['message' => 'Zona común actualizada exitosamente', 'common_area' => $commonArea], Response::HTTP_OK);
    }

    public function destroy(CommonArea $commonArea)
    {
        // Verificar si tiene asignaciones activas
        if ($commonArea->activeAssignments()->count() > 0) {
            return response(['message' => 'No se puede eliminar la zona común porque tiene asignaciones activas'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $commonArea->delete();

        return response(['message' => 'Zona común eliminada exitosamente'], Response::HTTP_OK);
    }
}
