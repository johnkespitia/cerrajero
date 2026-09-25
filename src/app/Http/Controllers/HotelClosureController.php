<?php

namespace App\Http\Controllers;

use App\Models\HotelClosure;
use App\Services\HotelClosureService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HotelClosureController extends Controller
{
    public function __construct(protected HotelClosureService $closureService) {}

    public function index(Request $request)
    {
        $query = HotelClosure::with('createdBy')->orderBy('start_date', 'desc');
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('per_page')) {
            return response()->json($query->paginate((int) $request->per_page));
        }
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $validation = $this->closureService->validateClosureCreation($request->start_date, $request->end_date);
        if (!$validation['valid']) {
            return response()->json([
                'message' => $validation['message'],
                'conflicts' => $validation['conflicts'],
            ], 422);
        }

        $closure = HotelClosure::create([
            'start_date' => Carbon::parse($request->start_date)->toDateString(),
            'end_date' => Carbon::parse($request->end_date)->toDateString(),
            'reason' => $request->reason,
            'created_by' => $request->user()->id ?? null,
            'status' => 'active',
        ]);

        return response()->json($closure->load('createdBy'), 201);
    }

    public function show(HotelClosure $hotelClosure)
    {
        return response()->json($hotelClosure->load('createdBy'));
    }

    public function update(Request $request, HotelClosure $hotelClosure)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive,cancelled',
        ]);

        $start = Carbon::parse($request->start_date)->toDateString();
        $end = Carbon::parse($request->end_date)->toDateString();

        $overlapping = HotelClosure::active()
            ->where('id', '!=', $hotelClosure->id)
            ->overlapping($start, $end)
            ->exists();
        if ($overlapping) {
            return response()->json(['message' => 'Ya existe otro cierre que solapa con ese periodo.'], 422);
        }

        if (($request->status ?? $hotelClosure->status) === 'active') {
            $conflicts = $this->closureService->getConflictingReservations($start, $end);
            $conflicts = $conflicts->filter(fn($r) => true);
            // Exclude self not needed, but check reservations
            if ($conflicts->isNotEmpty()) {
                return response()->json([
                    'message' => 'Existen reservas activas que solapan con el periodo.',
                    'conflicts' => $conflicts,
                ], 422);
            }
        }

        $hotelClosure->update([
            'start_date' => $start,
            'end_date' => $end,
            'reason' => $request->reason ?? $hotelClosure->reason,
            'status' => $request->status ?? $hotelClosure->status,
        ]);

        return response()->json($hotelClosure->fresh()->load('createdBy'));
    }

    public function destroy(HotelClosure $hotelClosure)
    {
        $conflicts = $this->closureService->getConflictingReservations(
            $hotelClosure->start_date->format('Y-m-d'),
            $hotelClosure->end_date->format('Y-m-d')
        );
        if ($conflicts->isNotEmpty() && $hotelClosure->status === 'active') {
            return response()->json([
                'message' => 'No se puede eliminar: existen reservas activas en ese periodo.',
                'conflicts' => $conflicts,
            ], 422);
        }

        $hotelClosure->delete();
        return response()->json(['message' => 'Cierre eliminado.']);
    }
}
