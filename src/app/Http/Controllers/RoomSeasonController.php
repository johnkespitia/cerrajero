<?php

namespace App\Http\Controllers;

use App\Models\RoomSeason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomSeasonController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomSeason::with('roomType');

        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $seasons = $query
            ->orderBy('room_type_id')
            ->orderBy('start_date')
            ->get();

        return response()->json($seasons);
    }

    public function show(RoomSeason $roomSeason)
    {
        $roomSeason->load('roomType');

        return response()->json($roomSeason);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $season = RoomSeason::create($this->payloadFromRequest($request));

        $season->load('roomType');

        return response()->json([
            'message' => 'Temporada creada exitosamente',
            'season' => $season,
        ], 201);
    }

    public function update(Request $request, RoomSeason $roomSeason)
    {
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $roomSeason->update($request->only([
            'room_type_id',
            'name',
            'start_date',
            'end_date',
            'price_multiplier',
            'fixed_price',
            'active',
        ]));

        $roomSeason->load('roomType');

        return response()->json([
            'message' => 'Temporada actualizada exitosamente',
            'season' => $roomSeason,
        ]);
    }

    public function destroy(RoomSeason $roomSeason)
    {
        $roomSeason->delete();

        return response()->json([
            'message' => 'Temporada eliminada exitosamente',
        ], 204);
    }

    private function rules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'room_type_id' => "{$required}|exists:room_types,id",
            'name' => "{$required}|string|max:255",
            'start_date' => "{$required}|date",
            'end_date' => "{$required}|date|after_or_equal:start_date",
            'price_multiplier' => 'nullable|numeric|min:0',
            'fixed_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
        ];
    }

    private function payloadFromRequest(Request $request, bool $isUpdate = false): array
    {
        $data = [
            'room_type_id' => $request->room_type_id,
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'price_multiplier' => $request->input('price_multiplier', 1.00),
            'fixed_price' => $request->filled('fixed_price') ? $request->fixed_price : null,
            'active' => $request->boolean('active', true),
        ];

        if ($isUpdate) {
            return array_filter(
                $data,
                fn ($value, $key) => $request->has($key) || $key === 'active',
                ARRAY_FILTER_USE_BOTH
            );
        }

        return $data;
    }
}
