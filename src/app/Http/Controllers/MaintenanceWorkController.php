<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceWork;
use App\Models\Room;
use App\Models\CommonArea;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MaintenanceWorkController extends Controller
{
    /**
     * Listar trabajos de mantenimiento
     */
    public function index(Request $request)
    {
        $query = MaintenanceWork::with(['maintainable', 'supplier', 'maintenanceRequest', 'performedBy']);

        // Filtros
        if ($request->has('room_id')) {
            $query->where('maintainable_type', Room::class)
                  ->where('maintainable_id', $request->room_id);
        }

        if ($request->has('common_area_id')) {
            $query->where('maintainable_type', CommonArea::class)
                  ->where('maintainable_id', $request->common_area_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('maintenance_request_id')) {
            $query->where('maintenance_request_id', $request->maintenance_request_id);
        }

        if ($request->has('work_type')) {
            $query->where('work_type', $request->work_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('work_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('work_date', '<=', $request->date_to);
        }

        $works = $query->orderBy('work_date', 'desc')
                      ->orderBy('work_start_time', 'desc')
                      ->get();

        return response($works, Response::HTTP_OK);
    }

    /**
     * Crear trabajo de mantenimiento
     */
    public function store(Request $request)
    {
        // Normalizar el maintainable_type
        $maintainableType = str_replace('\\\\', '\\', $request->maintainable_type ?? '');
        
        $validation = Validator::make([
            'maintenance_request_id' => $request->maintenance_request_id,
            'maintainable_type' => $maintainableType,
            'maintainable_id' => $request->maintainable_id,
            'supplier_id' => $request->supplier_id,
            'work_type' => $request->work_type,
            'work_date' => $request->work_date,
            'work_start_time' => $request->work_start_time,
            'work_end_time' => $request->work_end_time,
            'description' => $request->description,
            'materials_used' => $request->materials_used,
            'labor_cost' => $request->labor_cost,
            'materials_cost' => $request->materials_cost,
            'warranty_start_date' => $request->warranty_start_date,
            'warranty_end_date' => $request->warranty_end_date,
            'warranty_months' => $request->warranty_months,
            'warranty_terms' => $request->warranty_terms,
            'invoice_number' => $request->invoice_number,
            'invoice_date' => $request->invoice_date,
            'invoice_file_url' => $request->invoice_file_url,
            'status' => $request->status,
            'quality_rating' => $request->quality_rating,
            'notes' => $request->notes,
        ], [
            'maintenance_request_id' => 'nullable|exists:maintenance_requests,id',
            'maintainable_type' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, [Room::class, CommonArea::class])) {
                    $fail('The selected maintainable type is invalid.');
                }
            }],
            'maintainable_id' => 'required|integer',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'work_type' => 'required|in:repair,replacement,installation,maintenance,inspection',
            'work_date' => 'required|date',
            'work_start_time' => 'nullable|date_format:H:i',
            'work_end_time' => 'nullable|date_format:H:i',
            'description' => 'required|string',
            'materials_used' => 'nullable|string',
            'labor_cost' => 'nullable|numeric|min:0',
            'materials_cost' => 'nullable|numeric|min:0',
            'warranty_start_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date|after_or_equal:warranty_start_date',
            'warranty_months' => 'nullable|integer|min:1',
            'warranty_terms' => 'nullable|string',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'invoice_file_url' => 'nullable|string|max:500',
            'status' => 'nullable|in:completed,in_progress,cancelled',
            'quality_rating' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la ubicación existe
        $maintainable = $maintainableType::find($request->maintainable_id);
        if (!$maintainable) {
            return response(['message' => 'La ubicación especificada no existe'], Response::HTTP_NOT_FOUND);
        }

        // Calcular costo total
        $totalCost = ($request->labor_cost ?? 0) + ($request->materials_cost ?? 0);

        // Calcular fecha de fin de garantía si se proporciona meses de garantía
        $warrantyEndDate = $request->warranty_end_date;
        if (!$warrantyEndDate && $request->warranty_start_date && $request->warranty_months) {
            $warrantyEndDate = Carbon::parse($request->warranty_start_date)->addMonths($request->warranty_months)->toDateString();
        }

        $work = MaintenanceWork::create([
            'maintenance_request_id' => $request->maintenance_request_id,
            'maintainable_type' => $maintainableType,
            'maintainable_id' => $request->maintainable_id,
            'supplier_id' => $request->supplier_id,
            'work_type' => $request->work_type,
            'work_date' => $request->work_date,
            'work_start_time' => $request->work_start_time,
            'work_end_time' => $request->work_end_time,
            'description' => $request->description,
            'materials_used' => $request->materials_used,
            'labor_cost' => $request->labor_cost ?? 0,
            'materials_cost' => $request->materials_cost ?? 0,
            'total_cost' => $totalCost,
            'warranty_start_date' => $request->warranty_start_date,
            'warranty_end_date' => $warrantyEndDate,
            'warranty_months' => $request->warranty_months,
            'warranty_terms' => $request->warranty_terms,
            'invoice_number' => $request->invoice_number,
            'invoice_date' => $request->invoice_date,
            'invoice_file_url' => $request->invoice_file_url,
            'status' => $request->status ?? 'completed',
            'quality_rating' => $request->quality_rating,
            'notes' => $request->notes,
            'performed_by' => auth()->id(),
        ]);

        // Si hay una solicitud relacionada y el trabajo está completado, actualizar la solicitud
        if ($work->maintenance_request_id && $work->status === 'completed') {
            $maintenanceRequest = $work->maintenanceRequest;
            if ($maintenanceRequest && $maintenanceRequest->status !== 'completed') {
                $maintenanceRequest->update([
                    'status' => 'completed',
                    'completed_date' => $work->work_date,
                    'completed_time' => $work->work_end_time ?? $work->work_start_time,
                    'resolution_notes' => $work->description,
                ]);
            }
        }

        $work->load(['maintainable', 'supplier', 'maintenanceRequest', 'performedBy']);

        return response([
            'message' => 'Trabajo de mantenimiento registrado exitosamente',
            'work' => $work
        ], Response::HTTP_CREATED);
    }

    /**
     * Ver detalle de trabajo
     */
    public function show(MaintenanceWork $maintenanceWork)
    {
        $maintenanceWork->load(['maintainable', 'supplier', 'maintenanceRequest', 'performedBy']);
        return response($maintenanceWork, Response::HTTP_OK);
    }

    /**
     * Actualizar trabajo
     */
    public function update(Request $request, MaintenanceWork $maintenanceWork)
    {
        $validation = Validator::make($request->all(), [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'work_type' => 'sometimes|in:repair,replacement,installation,maintenance,inspection',
            'work_date' => 'sometimes|date',
            'work_start_time' => 'nullable|date_format:H:i',
            'work_end_time' => 'nullable|date_format:H:i',
            'description' => 'sometimes|required|string',
            'materials_used' => 'nullable|string',
            'labor_cost' => 'nullable|numeric|min:0',
            'materials_cost' => 'nullable|numeric|min:0',
            'warranty_start_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date|after_or_equal:warranty_start_date',
            'warranty_months' => 'nullable|integer|min:1',
            'warranty_terms' => 'nullable|string',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'invoice_file_url' => 'nullable|string|max:500',
            'status' => 'sometimes|in:completed,in_progress,cancelled',
            'quality_rating' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Recalcular costo total si cambian los costos
        if ($request->has('labor_cost') || $request->has('materials_cost')) {
            $laborCost = $request->labor_cost ?? $maintenanceWork->labor_cost;
            $materialsCost = $request->materials_cost ?? $maintenanceWork->materials_cost;
            $request->merge(['total_cost' => $laborCost + $materialsCost]);
        }

        $maintenanceWork->update($request->all());
        $maintenanceWork->load(['maintainable', 'supplier', 'maintenanceRequest', 'performedBy']);

        return response([
            'message' => 'Trabajo de mantenimiento actualizado exitosamente',
            'work' => $maintenanceWork
        ], Response::HTTP_OK);
    }

    /**
     * Obtener trabajos de una habitación
     */
    public function getByRoom($roomId)
    {
        $works = MaintenanceWork::where('maintainable_type', Room::class)
            ->where('maintainable_id', $roomId)
            ->with(['supplier', 'maintenanceRequest', 'performedBy'])
            ->orderBy('work_date', 'desc')
            ->get();

        return response($works, Response::HTTP_OK);
    }

    /**
     * Obtener trabajos de una zona común
     */
    public function getByCommonArea($areaId)
    {
        $works = MaintenanceWork::where('maintainable_type', CommonArea::class)
            ->where('maintainable_id', $areaId)
            ->with(['supplier', 'maintenanceRequest', 'performedBy'])
            ->orderBy('work_date', 'desc')
            ->get();

        return response($works, Response::HTTP_OK);
    }

    /**
     * Obtener trabajos de un proveedor
     */
    public function getBySupplier($supplierId)
    {
        $works = MaintenanceWork::where('supplier_id', $supplierId)
            ->with(['maintainable', 'maintenanceRequest', 'performedBy'])
            ->orderBy('work_date', 'desc')
            ->get();

        return response($works, Response::HTTP_OK);
    }

    /**
     * Obtener garantías próximas a vencer
     */
    public function getWarrantyExpiring(Request $request)
    {
        $days = $request->input('days', 30); // Por defecto, próximos 30 días
        
        $works = MaintenanceWork::whereNotNull('warranty_end_date')
            ->where('warranty_end_date', '>=', Carbon::today())
            ->where('warranty_end_date', '<=', Carbon::today()->addDays($days))
            ->with(['maintainable', 'supplier', 'maintenanceRequest'])
            ->orderBy('warranty_end_date', 'asc')
            ->get();

        return response($works, Response::HTTP_OK);
    }

    /**
     * Reporte de costos
     */
    public function getCostsReport(Request $request)
    {
        $query = MaintenanceWork::query();

        // Filtros
        if ($request->has('date_from')) {
            $query->where('work_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('work_date', '<=', $request->date_to);
        }

        if ($request->has('room_id')) {
            $query->where('maintainable_type', Room::class)
                  ->where('maintainable_id', $request->room_id);
        }

        if ($request->has('common_area_id')) {
            $query->where('maintainable_type', CommonArea::class)
                  ->where('maintainable_id', $request->common_area_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $works = $query->get();

        $report = [
            'total_works' => $works->count(),
            'total_cost' => $works->sum('total_cost'),
            'total_labor_cost' => $works->sum('labor_cost'),
            'total_materials_cost' => $works->sum('materials_cost'),
            'average_cost' => $works->count() > 0 ? $works->avg('total_cost') : 0,
            'by_work_type' => $works->groupBy('work_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_cost' => $group->sum('total_cost'),
                ];
            }),
            'by_supplier' => $works->whereNotNull('supplier_id')->groupBy('supplier_id')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_cost' => $group->sum('total_cost'),
                ];
            }),
        ];

        return response($report, Response::HTTP_OK);
    }
}
