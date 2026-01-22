<?php

namespace App\Http\Controllers;

use App\Models\RoomInventoryAssignment;
use App\Models\Room;
use App\Models\CommonArea;
use App\Services\RoomInventoryAuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RoomInventoryAssignmentController extends Controller
{
    protected $auditService;

    public function __construct(RoomInventoryAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $query = RoomInventoryAssignment::with(['item.category', 'assignable', 'assignedBy', 'lastCheckedBy', 'repairedBy']);

        // Filtros
        if ($request->has('room_id')) {
            $query->where('assignable_type', Room::class)
                  ->where('assignable_id', $request->room_id);
        }

        if ($request->has('common_area_id')) {
            $query->where('assignable_type', CommonArea::class)
                  ->where('assignable_id', $request->common_area_id);
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $assignments = $query->orderBy('created_at', 'desc')->get();

        return response($assignments, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        // Normalizar el assignable_type (puede venir con doble backslash desde JSON)
        $assignableType = str_replace('\\\\', '\\', $request->assignable_type);
        
        $validation = Validator::make([
            'assignable_type' => $assignableType,
            'assignable_id' => $request->assignable_id,
            'item_id' => $request->item_id,
            'quantity' => $request->quantity,
            'status' => $request->status,
            'condition_notes' => $request->condition_notes,
        ], [
            'assignable_type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, [Room::class, CommonArea::class])) {
                    $fail('The selected assignable type is invalid.');
                }
            }],
            'assignable_id' => 'required|integer',
            'item_id' => 'required|exists:room_inventory_items,id',
            'quantity' => 'required|integer|min:1',
            'status' => 'nullable|in:available,in_use,damaged,maintenance,missing,replaced',
            'condition_notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la ubicación existe
        $assignable = $assignableType::find($request->assignable_id);

        if (!$assignable) {
            return response(['message' => 'La ubicación especificada no existe'], Response::HTTP_NOT_FOUND);
        }

        if (!$assignable->active) {
            return response(['message' => 'La ubicación no está activa'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $assignment = RoomInventoryAssignment::create([
                'assignable_type' => $assignableType,
                'assignable_id' => $request->assignable_id,
                'item_id' => $request->item_id,
                'quantity' => $request->quantity,
                'status' => $request->status ?? 'in_use', // Estado por defecto: en uso cuando se asigna
                'condition_notes' => $request->condition_notes,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]);

            // Registrar en historial
            $this->auditService->logAssignment($assignment, auth()->id(), $request);

            $assignment->load(['item.category', 'assignable', 'assignedBy']);

            DB::commit();
            return response(['message' => 'Asignación creada exitosamente', 'assignment' => $assignment], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => 'Error al crear la asignación: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(RoomInventoryAssignment $roomInventoryAssignment)
    {
        $roomInventoryAssignment->load(['item.category', 'assignable', 'assignedBy', 'lastCheckedBy', 'history.user']);
        return response($roomInventoryAssignment, Response::HTTP_OK);
    }

    public function update(Request $request, RoomInventoryAssignment $roomInventoryAssignment)
    {
        $validation = Validator::make($request->all(), [
            'quantity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:available,in_use,damaged,maintenance,missing,replaced',
            'condition_notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldQuantity = $roomInventoryAssignment->quantity;
        $oldStatus = $roomInventoryAssignment->status;

        $roomInventoryAssignment->update($request->only(['quantity', 'status', 'condition_notes']));

        // Registrar cambios en historial si hay cambios
        if ($oldStatus !== $roomInventoryAssignment->status) {
            $this->auditService->logStatusChange($roomInventoryAssignment, $oldStatus, $roomInventoryAssignment->status, null, $request);
        }

        if ($oldQuantity !== $roomInventoryAssignment->quantity) {
            $this->auditService->log('quantity_changed', [
                'assignment_id' => $roomInventoryAssignment->id,
                'assignable_type' => $roomInventoryAssignment->assignable_type,
                'assignable_id' => $roomInventoryAssignment->assignable_id,
                'item_id' => $roomInventoryAssignment->item_id,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $roomInventoryAssignment->quantity,
                'notes' => 'Cantidad actualizada',
            ], auth()->id(), $request);
        }

        $roomInventoryAssignment->load(['item.category', 'assignable']);

        return response(['message' => 'Asignación actualizada exitosamente', 'assignment' => $roomInventoryAssignment], Response::HTTP_OK);
    }

    public function updateStatus(Request $request, RoomInventoryAssignment $roomInventoryAssignment)
    {
        $validation = Validator::make($request->all(), [
            'status' => 'required|in:available,in_use,damaged,maintenance,missing,replaced',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldStatus = $roomInventoryAssignment->status;
        $newStatus = $request->status;
        
        // Estados que requieren desasignación automática
        $problemStatuses = ['damaged', 'maintenance', 'missing', 'replaced'];
        $shouldDeactivate = in_array($newStatus, $problemStatuses);
        
        // Estados que requieren desactivar el artículo mismo
        $itemDeactivationStatuses = ['damaged', 'missing', 'replaced'];
        $shouldDeactivateItem = in_array($newStatus, $itemDeactivationStatuses);

        DB::beginTransaction();
        try {
            $updateData = ['status' => $newStatus];
            
            // Si el nuevo estado es problemático, desactivar la asignación
            if ($shouldDeactivate) {
                $updateData['active'] = false;
            }
            
            $roomInventoryAssignment->update($updateData);
            
            // Si el estado requiere desactivar el artículo mismo, hacerlo
            if ($shouldDeactivateItem) {
                $roomInventoryAssignment->item->update(['active' => false]);
            }

            // Registrar cambio de estado en historial
            $this->auditService->logStatusChange($roomInventoryAssignment, $oldStatus, $newStatus, $request->notes, $request);
            
            // Si se desactivó, registrar la remoción
            if ($shouldDeactivate) {
                $this->auditService->logRemoval($roomInventoryAssignment, "Artículo desasignado automáticamente por cambio de estado a: {$newStatus}. " . ($request->notes ?? ''), $request);
            }

            $roomInventoryAssignment->load(['item.category', 'assignable']);

            DB::commit();
            $message = $shouldDeactivate 
                ? 'Estado actualizado y artículo desasignado automáticamente' 
                : 'Estado actualizado exitosamente';
            if ($shouldDeactivateItem) {
                $message .= '. El artículo ha sido desactivado.';
            }
            return response(['message' => $message, 'assignment' => $roomInventoryAssignment], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => 'Error al actualizar el estado: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function move(Request $request, RoomInventoryAssignment $roomInventoryAssignment)
    {
        // Normalizar el new_assignable_type (puede venir con doble backslash desde JSON)
        $newAssignableType = str_replace('\\\\', '\\', $request->new_assignable_type);
        
        $validation = Validator::make([
            'new_assignable_type' => $newAssignableType,
            'new_assignable_id' => $request->new_assignable_id,
            'notes' => $request->notes,
        ], [
            'new_assignable_type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, [Room::class, CommonArea::class])) {
                    $fail('The selected assignable type is invalid.');
                }
            }],
            'new_assignable_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la nueva ubicación existe
        $newAssignable = $newAssignableType::find($request->new_assignable_id);

        if (!$newAssignable) {
            return response(['message' => 'La nueva ubicación no existe'], Response::HTTP_NOT_FOUND);
        }

        if (!$newAssignable->active) {
            return response(['message' => 'La nueva ubicación no está activa'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldAssignableType = $roomInventoryAssignment->assignable_type;
        $oldAssignableId = $roomInventoryAssignment->assignable_id;

        DB::beginTransaction();
        try {
            $roomInventoryAssignment->update([
                'assignable_type' => $newAssignableType,
                'assignable_id' => $request->new_assignable_id,
            ]);

            // Registrar movimiento en historial
            $this->auditService->logMove(
                $roomInventoryAssignment,
                $oldAssignableType,
                $oldAssignableId,
                $newAssignableType,
                $request->new_assignable_id,
                $request->notes,
                $request
            );

            $roomInventoryAssignment->load(['item.category', 'assignable']);

            DB::commit();
            return response(['message' => 'Artículo movido exitosamente', 'assignment' => $roomInventoryAssignment], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => 'Error al mover el artículo: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function check(Request $request, RoomInventoryAssignment $roomInventoryAssignment)
    {
        $validation = Validator::make($request->all(), [
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryAssignment->update([
            'last_checked_at' => now(),
            'last_checked_by' => auth()->id(),
        ]);

        // Registrar verificación en historial
        $this->auditService->logCheck($roomInventoryAssignment, $request->notes, $request);

        $roomInventoryAssignment->load(['item.category', 'assignable', 'lastCheckedBy']);

        return response(['message' => 'Verificación registrada exitosamente', 'assignment' => $roomInventoryAssignment], Response::HTTP_OK);
    }

    public function registerRepair(Request $request, RoomInventoryAssignment $roomInventoryAssignment)
    {
        $validation = Validator::make($request->all(), [
            'repair_date' => 'required|date|before_or_equal:today',
            'repair_notes' => 'nullable|string',
            'maintenance_warranty_expires_at' => 'nullable|date|after:repair_date',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verificar que el estado permita reparación (maintenance o damaged)
        $repairableStatuses = ['maintenance', 'damaged'];
        if (!in_array($roomInventoryAssignment->status, $repairableStatuses)) {
            return response(['message' => 'Solo se pueden registrar reparaciones para artículos en mantenimiento o dañados'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $roomInventoryAssignment->status;
            $oldAssignableType = $roomInventoryAssignment->assignable_type;
            $oldAssignableId = $roomInventoryAssignment->assignable_id;
            
            // Actualizar con datos de reparación, cambiar estado a "available" y reactivar la asignación
            // El artículo queda disponible y activo para ser reasignado a otra ubicación
            $roomInventoryAssignment->update([
                'repair_date' => $request->repair_date,
                'repair_notes' => $request->repair_notes,
                'maintenance_warranty_expires_at' => $request->maintenance_warranty_expires_at,
                'repaired_by' => auth()->id(),
                'status' => 'available',
                'active' => true, // Reactivar la asignación
            ]);

            // Reactivar el artículo mismo si estaba desactivado
            if (!$roomInventoryAssignment->item->active) {
                $roomInventoryAssignment->item->update(['active' => true]);
            }

            // Registrar cambio de estado en historial
            $this->auditService->logStatusChange(
                $roomInventoryAssignment, 
                $oldStatus, 
                'available', 
                "Reparación registrada. Artículo disponible y activo para reasignación. " . ($request->repair_notes ?? ''), 
                $request
            );

            $roomInventoryAssignment->load(['item.category', 'assignable', 'repairedBy']);

            DB::commit();
            return response([
                'message' => 'Reparación registrada exitosamente. Artículo disponible y activo para reasignación.',
                'assignment' => $roomInventoryAssignment
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => 'Error al registrar la reparación: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(RoomInventoryAssignment $roomInventoryAssignment)
    {
        DB::beginTransaction();
        try {
            // Registrar remoción en historial antes de desactivar
            $this->auditService->logRemoval($roomInventoryAssignment, 'Asignación desactivada', request());

            // Marcar como inactiva en lugar de eliminar
            $roomInventoryAssignment->update(['active' => false]);

            DB::commit();
            return response(['message' => 'Asignación removida exitosamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => 'Error al remover la asignación: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
