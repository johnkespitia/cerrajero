<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\CommonArea;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class MaintenanceRequestController extends Controller
{
    /**
     * Listar solicitudes de mantenimiento
     */
    public function index(Request $request)
    {
        $query = MaintenanceRequest::with(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem']);

        // Filtros
        if ($request->has('room_id')) {
            $query->where('maintainable_type', Room::class)
                  ->where('maintainable_id', $request->room_id);
        }

        if ($request->has('common_area_id')) {
            $query->where('maintainable_type', CommonArea::class)
                  ->where('maintainable_id', $request->common_area_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('issue_type')) {
            $query->where('issue_type', $request->issue_type);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('date_from')) {
            $query->where('reported_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('reported_date', '<=', $request->date_to);
        }

        $requests = $query->with('maintenanceWorks.supplier', 'maintenanceWorks.performedBy')
                         ->orderBy('reported_date', 'desc')
                         ->orderBy('priority', 'desc')
                         ->get();

        return response($requests, Response::HTTP_OK);
    }

    /**
     * Crear solicitud de mantenimiento
     */
    public function store(Request $request)
    {
        // Normalizar el maintainable_type
        $maintainableType = str_replace('\\\\', '\\', $request->maintainable_type ?? '');
        
        $validation = Validator::make([
            'maintainable_type' => $maintainableType,
            'maintainable_id' => $request->maintainable_id,
            'reported_by' => $request->reported_by ?? auth()->id(),
            'reported_date' => $request->reported_date ?? now()->toDateString(),
            'reported_time' => $request->reported_time,
            'issue_type' => $request->issue_type,
            'priority' => $request->priority,
            'title' => $request->title,
            'description' => $request->description,
            'location_detail' => $request->location_detail,
            'related_inventory_item_id' => $request->related_inventory_item_id,
        ], [
            'maintainable_type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, [Room::class, CommonArea::class])) {
                    $fail('The selected maintainable type is invalid.');
                }
            }],
            'maintainable_id' => 'required|integer',
            'reported_by' => 'required|exists:users,id',
            'reported_date' => 'required|date',
            'reported_time' => 'nullable|date_format:H:i',
            'issue_type' => 'required|in:damage,repair,preventive,inspection,other',
            'priority' => 'required|in:low,medium,high,urgent',
            'title' => 'required|string|max:250',
            'description' => 'required|string',
            'location_detail' => 'nullable|string|max:250',
            'related_inventory_item_id' => 'nullable|exists:room_inventory_items,id',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la ubicación existe
        $maintainable = $maintainableType::find($request->maintainable_id);
        if (!$maintainable) {
            return response(['message' => 'La ubicación especificada no existe'], Response::HTTP_NOT_FOUND);
        }

        $maintenanceRequest = MaintenanceRequest::create([
            'maintainable_type' => $maintainableType,
            'maintainable_id' => $request->maintainable_id,
            'reported_by' => $request->reported_by ?? auth()->id(),
            'reported_date' => $request->reported_date ?? now()->toDateString(),
            'reported_time' => $request->reported_time ?? now()->format('H:i'),
            'issue_type' => $request->issue_type,
            'priority' => $request->priority,
            'title' => $request->title,
            'description' => $request->description,
            'location_detail' => $request->location_detail,
            'related_inventory_item_id' => $request->related_inventory_item_id,
            'status' => 'pending',
        ]);

        $maintenanceRequest->load(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem']);

        return response([
            'message' => 'Solicitud de mantenimiento creada exitosamente',
            'request' => $maintenanceRequest
        ], Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de solicitud
     */
    public function show(MaintenanceRequest $maintenanceRequest)
    {
        $maintenanceRequest->load([
            'maintainable', 
            'reportedBy', 
            'assignedTo', 
            'relatedInventoryItem',
            'maintenanceWorks.supplier',
            'maintenanceWorks.performedBy'
        ]);
        return response($maintenanceRequest, Response::HTTP_OK);
    }

    /**
     * Actualizar solicitud
     */
    public function update(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validation = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:250',
            'description' => 'sometimes|required|string',
            'location_detail' => 'nullable|string|max:250',
            'issue_type' => 'sometimes|in:damage,repair,preventive,inspection,other',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:pending,assigned,in_progress,completed,cancelled,on_hold',
            'assigned_to' => 'nullable|exists:users,id',
            'assigned_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'completed_date' => 'nullable|date',
            'completed_time' => 'nullable|date_format:H:i',
            'resolution_notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Si se asigna, actualizar assigned_date
        if ($request->has('assigned_to') && $request->assigned_to && !$maintenanceRequest->assigned_to) {
            $request->merge(['assigned_date' => now()->toDateString()]);
        }

        // Si se completa, actualizar completed_date y completed_time
        if ($request->has('status') && $request->status === 'completed' && $maintenanceRequest->status !== 'completed') {
            $request->merge([
                'completed_date' => now()->toDateString(),
                'completed_time' => now()->format('H:i')
            ]);
        }

        $oldStatus = $maintenanceRequest->status;
        $newStatus = $request->has('status') ? $request->status : $oldStatus;

        $maintenanceRequest->update($request->all());
        $maintenanceRequest->load(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem', 'maintenanceWorks']);

        // Actualizar estado de la habitación/zona común si es necesario
        $this->updateMaintainableStatus($maintenanceRequest, $oldStatus, $newStatus);

        return response([
            'message' => 'Solicitud de mantenimiento actualizada exitosamente',
            'request' => $maintenanceRequest
        ], Response::HTTP_OK);
    }

    /**
     * Asignar solicitud
     */
    public function assign(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validation = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:users,id',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldStatus = $maintenanceRequest->status;
        
        $maintenanceRequest->update([
            'assigned_to' => $request->assigned_to,
            'assigned_date' => now()->toDateString(),
            'status' => 'assigned',
        ]);

        $maintenanceRequest->load(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem']);

        // Actualizar estado de la habitación/zona común si es necesario
        $this->updateMaintainableStatus($maintenanceRequest, $oldStatus, 'assigned');

        return response([
            'message' => 'Solicitud asignada exitosamente',
            'request' => $maintenanceRequest
        ], Response::HTTP_OK);
    }

    /**
     * Completar solicitud
     */
    public function complete(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validation = Validator::make($request->all(), [
            'resolution_notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldStatus = $maintenanceRequest->status;
        
        $maintenanceRequest->update([
            'status' => 'completed',
            'completed_date' => now()->toDateString(),
            'completed_time' => now()->format('H:i'),
            'resolution_notes' => $request->resolution_notes,
        ]);

        $maintenanceRequest->load(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem', 'maintenanceWorks']);

        // Actualizar estado de la habitación/zona común
        $this->updateMaintainableStatus($maintenanceRequest, $oldStatus, 'completed');

        return response([
            'message' => 'Solicitud completada exitosamente',
            'request' => $maintenanceRequest
        ], Response::HTTP_OK);
    }

    /**
     * Cancelar solicitud
     */
    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        $validation = Validator::make($request->all(), [
            'resolution_notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldStatus = $maintenanceRequest->status;
        
        $maintenanceRequest->update([
            'status' => 'cancelled',
            'resolution_notes' => $request->resolution_notes ?? 'Solicitud cancelada',
        ]);

        $maintenanceRequest->load(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem']);

        // Actualizar estado de la habitación/zona común
        $this->updateMaintainableStatus($maintenanceRequest, $oldStatus, 'cancelled');

        return response([
            'message' => 'Solicitud cancelada exitosamente',
            'request' => $maintenanceRequest
        ], Response::HTTP_OK);
    }

    /**
     * Obtener solicitudes de una habitación
     */
    public function getByRoom($roomId)
    {
        $requests = MaintenanceRequest::where('maintainable_type', Room::class)
            ->where('maintainable_id', $roomId)
            ->with(['reportedBy', 'assignedTo', 'relatedInventoryItem'])
            ->orderBy('reported_date', 'desc')
            ->get();

        return response($requests, Response::HTTP_OK);
    }

    /**
     * Obtener solicitudes de una zona común
     */
    public function getByCommonArea($areaId)
    {
        $requests = MaintenanceRequest::where('maintainable_type', CommonArea::class)
            ->where('maintainable_id', $areaId)
            ->with(['reportedBy', 'assignedTo', 'relatedInventoryItem'])
            ->orderBy('reported_date', 'desc')
            ->get();

        return response($requests, Response::HTTP_OK);
    }

    /**
     * Actualizar el estado de la habitación/zona común basado en el estado del mantenimiento
     */
    private function updateMaintainableStatus(MaintenanceRequest $maintenanceRequest, $oldStatus, $newStatus)
    {
        $maintainable = $maintenanceRequest->maintainable;
        
        // Solo procesar si es una habitación (las zonas comunes no tienen estado de disponibilidad)
        if (!$maintainable instanceof Room) {
            return;
        }

        // Si el mantenimiento pasa a 'in_progress' y es daño o reparación, poner habitación en maintenance
        if ($newStatus === 'in_progress' && in_array($maintenanceRequest->issue_type, ['damage', 'repair'])) {
            if ($maintainable->status !== 'maintenance') {
                $maintainable->update(['status' => 'maintenance']);
            }
        }
        // Si el mantenimiento se completa o cancela, verificar si debe restaurar el estado
        elseif (in_array($newStatus, ['completed', 'cancelled'])) {
            // Usar el método del modelo para actualizar el estado basado en todos los mantenimientos
            $maintainable->updateStatusBasedOnMaintenance();
        }
        // Si hay un mantenimiento urgente asignado o en progreso, también poner en maintenance
        elseif (in_array($newStatus, ['assigned', 'in_progress']) && 
                in_array($maintenanceRequest->priority, ['high', 'urgent']) &&
                in_array($maintenanceRequest->issue_type, ['damage', 'repair'])) {
            if ($maintainable->status !== 'maintenance') {
                $maintainable->update(['status' => 'maintenance']);
            }
        }
    }

    /**
     * Obtener solicitudes por estado
     */
    public function getByStatus($status)
    {
        $requests = MaintenanceRequest::where('status', $status)
            ->with(['maintainable', 'reportedBy', 'assignedTo', 'relatedInventoryItem'])
            ->orderBy('priority', 'desc')
            ->orderBy('reported_date', 'desc')
            ->get();

        return response($requests, Response::HTTP_OK);
    }
}
