<?php

namespace App\Http\Controllers;

use App\Models\CancellationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CancellationPolicyController extends Controller
{
    /**
     * Listar todas las políticas de cancelación
     */
    public function index(Request $request)
    {
        $query = CancellationPolicy::with('roomType');

        // Filtrar por activas si se solicita
        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Filtrar por tipo de aplicación
        if ($request->has('apply_to')) {
            $query->where('apply_to', $request->apply_to);
        }

        // Filtrar por tipo de habitación
        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Filtrar por tipo de reserva
        if ($request->has('reservation_type')) {
            $query->where('reservation_type', $request->reservation_type);
        }

        $policies = $query->orderBy('name')->get();

        return response()->json($policies);
    }

    /**
     * Obtener una política específica
     */
    public function show(CancellationPolicy $cancellationPolicy)
    {
        $cancellationPolicy->load('roomType');
        return response()->json($cancellationPolicy);
    }

    /**
     * Crear una nueva política de cancelación
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'policy_type' => 'required|in:free,partial,non_refundable',
            'cancellation_days_before' => 'nullable|integer|min:0',
            'penalty_percentage' => 'nullable|numeric|min:0|max:100',
            'penalty_fee' => 'nullable|numeric|min:0',
            'apply_to' => 'required|in:all,room_type,reservation_type',
            'room_type_id' => 'required_if:apply_to,room_type|nullable|exists:room_types,id',
            'reservation_type' => 'required_if:apply_to,reservation_type|nullable|in:room,day_pass',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Si se marca como por defecto, quitar el flag de otras políticas
            if ($request->boolean('is_default')) {
                CancellationPolicy::where('is_default', true)->update(['is_default' => false]);
            }

            $policy = CancellationPolicy::create([
                'name' => $request->name,
                'description' => $request->description,
                'policy_type' => $request->policy_type,
                'cancellation_days_before' => $request->cancellation_days_before,
                'penalty_percentage' => $request->penalty_percentage ?? 0,
                'penalty_fee' => $request->penalty_fee ?? 0,
                'apply_to' => $request->apply_to,
                'room_type_id' => $request->room_type_id,
                'reservation_type' => $request->reservation_type,
                'is_default' => $request->boolean('is_default', false),
                'active' => $request->boolean('active', true),
            ]);

            DB::commit();

            $policy->load('roomType');

            return response()->json([
                'message' => 'Política de cancelación creada exitosamente',
                'policy' => $policy
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la política de cancelación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una política de cancelación
     */
    public function update(Request $request, CancellationPolicy $cancellationPolicy)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'policy_type' => 'sometimes|in:free,partial,non_refundable',
            'cancellation_days_before' => 'nullable|integer|min:0',
            'penalty_percentage' => 'nullable|numeric|min:0|max:100',
            'penalty_fee' => 'nullable|numeric|min:0',
            'apply_to' => 'sometimes|in:all,room_type,reservation_type',
            'room_type_id' => 'required_if:apply_to,room_type|nullable|exists:room_types,id',
            'reservation_type' => 'required_if:apply_to,reservation_type|nullable|in:room,day_pass',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Si se marca como por defecto, quitar el flag de otras políticas
            if ($request->has('is_default') && $request->boolean('is_default')) {
                CancellationPolicy::where('id', '!=', $cancellationPolicy->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $cancellationPolicy->update($request->only([
                'name',
                'description',
                'policy_type',
                'cancellation_days_before',
                'penalty_percentage',
                'penalty_fee',
                'apply_to',
                'room_type_id',
                'reservation_type',
                'is_default',
                'active',
            ]));

            DB::commit();

            $cancellationPolicy->load('roomType');

            return response()->json([
                'message' => 'Política de cancelación actualizada exitosamente',
                'policy' => $cancellationPolicy
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la política de cancelación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una política de cancelación
     */
    public function destroy(CancellationPolicy $cancellationPolicy)
    {
        // Verificar si tiene reservas asociadas
        $reservationsCount = $cancellationPolicy->reservations()->count();
        
        if ($reservationsCount > 0) {
            return response()->json([
                'message' => "No se puede eliminar la política porque tiene {$reservationsCount} reserva(s) asociada(s). Puede desactivarla en su lugar.",
                'reservations_count' => $reservationsCount
            ], 422);
        }

        DB::beginTransaction();
        try {
            $cancellationPolicy->delete();
            DB::commit();

            return response()->json([
                'message' => 'Política de cancelación eliminada exitosamente'
            ], 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar la política de cancelación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener política aplicable para una reserva
     */
    public function getApplicablePolicy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_type_id' => 'nullable|exists:room_types,id',
            'reservation_type' => 'nullable|in:room,day_pass',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $policy = CancellationPolicy::getApplicablePolicy(
            $request->room_type_id,
            $request->reservation_type
        );

        if (!$policy) {
            return response()->json([
                'message' => 'No se encontró una política aplicable',
                'policy' => null
            ], 404);
        }

        $policy->load('roomType');

        return response()->json([
            'policy' => $policy
        ]);
    }
}

