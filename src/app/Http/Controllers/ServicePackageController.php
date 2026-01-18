<?php

namespace App\Http\Controllers;

use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ServicePackageController extends Controller
{
    public function index(Request $request)
    {
        $query = ServicePackage::with('additionalServices', 'roomType');

        if ($request->boolean('active_only')) {
            $query->active();
        }
        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        $items = $query->orderBy('name')->get();
        return response()->json($items);
    }

    public function show(ServicePackage $servicePackage)
    {
        $servicePackage->load('additionalServices', 'roomType');
        return response()->json($servicePackage);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'room_type_id' => 'nullable|exists:room_types,id',
            'status' => 'in:active,inactive',
            'additional_service_ids' => 'nullable|array',
            'additional_service_ids.*' => 'exists:additional_services,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $package = ServicePackage::create([
                'name' => $request->name,
                'description' => $request->description,
                'room_type_id' => $request->room_type_id,
                'status' => $request->input('status', 'active'),
            ]);

            if ($request->has('additional_service_ids') && is_array($request->additional_service_ids)) {
                $package->additionalServices()->sync($request->additional_service_ids);
            }

            DB::commit();
            $package->load('additionalServices', 'roomType');
            return response()->json($package, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear el paquete', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, ServicePackage $servicePackage)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'room_type_id' => 'nullable|exists:room_types,id',
            'status' => 'sometimes|in:active,inactive',
            'additional_service_ids' => 'nullable|array',
            'additional_service_ids.*' => 'exists:additional_services,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $servicePackage->update($request->only(['name', 'description', 'room_type_id', 'status']));

            if ($request->has('additional_service_ids')) {
                $servicePackage->additionalServices()->sync($request->additional_service_ids ?? []);
            }

            DB::commit();
            $servicePackage->load('additionalServices', 'roomType');
            return response()->json($servicePackage);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar el paquete', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(ServicePackage $servicePackage)
    {
        $servicePackage->additionalServices()->detach();
        $servicePackage->delete();
        return response()->json(null, 204);
    }
}
