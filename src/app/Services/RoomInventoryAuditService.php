<?php

namespace App\Services;

use App\Models\RoomInventoryHistory;
use App\Models\RoomInventoryAssignment;
use App\Models\Room;
use App\Models\CommonArea;
use Illuminate\Http\Request;

class RoomInventoryAuditService
{
    public function log($action, $data, $userId = null, Request $request = null)
    {
        return RoomInventoryHistory::create([
            'assignment_id' => $data['assignment_id'] ?? null,
            'assignable_type' => $data['assignable_type'] ?? null,
            'assignable_id' => $data['assignable_id'] ?? null,
            'item_id' => $data['item_id'],
            'action' => $action,
            'old_assignable_type' => $data['old_assignable_type'] ?? null,
            'old_assignable_id' => $data['old_assignable_id'] ?? null,
            'new_assignable_type' => $data['new_assignable_type'] ?? null,
            'new_assignable_id' => $data['new_assignable_id'] ?? null,
            'old_status' => $data['old_status'] ?? null,
            'new_status' => $data['new_status'] ?? null,
            'old_quantity' => $data['old_quantity'] ?? null,
            'new_quantity' => $data['new_quantity'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
        ]);
    }

    public function logAssignment(RoomInventoryAssignment $assignment, $userId = null, Request $request = null)
    {
        $locationName = $assignment->location_name;

        return $this->log('assigned', [
            'assignment_id' => $assignment->id,
            'assignable_type' => $assignment->assignable_type,
            'assignable_id' => $assignment->assignable_id,
            'item_id' => $assignment->item_id,
            'new_assignable_type' => $assignment->assignable_type,
            'new_assignable_id' => $assignment->assignable_id,
            'new_status' => $assignment->status,
            'new_quantity' => $assignment->quantity,
            'notes' => "Artículo asignado a {$locationName}",
        ], $userId, $request);
    }

    public function logStatusChange(RoomInventoryAssignment $assignment, $oldStatus, $newStatus, $notes = null, Request $request = null)
    {
        return $this->log('status_changed', [
            'assignment_id' => $assignment->id,
            'assignable_type' => $assignment->assignable_type,
            'assignable_id' => $assignment->assignable_id,
            'item_id' => $assignment->item_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
        ], auth()->id(), $request);
    }

    public function logMove(RoomInventoryAssignment $assignment, $oldAssignableType, $oldAssignableId, $newAssignableType, $newAssignableId, $notes = null, Request $request = null)
    {
        $oldLocation = $this->getLocationName($oldAssignableType, $oldAssignableId);
        $newLocation = $this->getLocationName($newAssignableType, $newAssignableId);

        return $this->log('moved', [
            'assignment_id' => $assignment->id,
            'assignable_type' => $newAssignableType,
            'assignable_id' => $newAssignableId,
            'item_id' => $assignment->item_id,
            'old_assignable_type' => $oldAssignableType,
            'old_assignable_id' => $oldAssignableId,
            'new_assignable_type' => $newAssignableType,
            'new_assignable_id' => $newAssignableId,
            'notes' => $notes ?? "Artículo movido de {$oldLocation} a {$newLocation}",
        ], auth()->id(), $request);
    }

    public function logCheck(RoomInventoryAssignment $assignment, $notes = null, Request $request = null)
    {
        return $this->log('checked', [
            'assignment_id' => $assignment->id,
            'assignable_type' => $assignment->assignable_type,
            'assignable_id' => $assignment->assignable_id,
            'item_id' => $assignment->item_id,
            'notes' => $notes ?? 'Verificación de inventario',
        ], auth()->id(), $request);
    }

    public function logRemoval(RoomInventoryAssignment $assignment, $notes = null, Request $request = null)
    {
        $locationName = $assignment->location_name;

        return $this->log('removed', [
            'assignment_id' => $assignment->id,
            'assignable_type' => $assignment->assignable_type,
            'assignable_id' => $assignment->assignable_id,
            'item_id' => $assignment->item_id,
            'old_assignable_type' => $assignment->assignable_type,
            'old_assignable_id' => $assignment->assignable_id,
            'notes' => $notes ?? "Artículo removido de {$locationName}",
        ], auth()->id(), $request);
    }

    protected function getLocationName($type, $id)
    {
        if (!$type || !$id) {
            return 'ubicación desconocida';
        }

        $model = $type::find($id);
        if ($model) {
            return $model->display_name ?? "Ubicación #{$id}";
        }
        return "Ubicación #{$id}";
    }
}
