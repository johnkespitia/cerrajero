<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::with('roomType')->orderBy('id')->get();
        return response($rooms, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'room_type_id' => 'required|exists:room_types,id',
            'number'       => 'nullable|string|max:50',
            'name'         => 'nullable|string|max:100',
            'capacity'     => 'required|integer|min:1',
            'max_capacity' => 'nullable|integer|min:1',
            'room_price'   => 'required|numeric|min:0',
            'status'       => 'nullable|string|max:50',
            'active'       => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->all();

        // Si no se envían capacidad o precio, tomar valores por defecto del tipo de habitación
        $roomType = RoomType::find($data['room_type_id']);
        if (!isset($data['capacity']) || !$data['capacity']) {
            $data['capacity'] = $roomType ? $roomType->default_capacity : 1;
        }
        if (!isset($data['max_capacity']) || !$data['max_capacity']) {
            $data['max_capacity'] = $roomType ? $roomType->max_capacity : $data['capacity'];
        }
        if (!isset($data['room_price']) || $data['room_price'] === null || $data['room_price'] === '') {
            $data['room_price'] = $roomType && $roomType->base_price !== null ? $roomType->base_price : 0;
        }

        $room = Room::create($data);

        return response($room->load('roomType'), Response::HTTP_CREATED);
    }

    public function show(Room $room)
    {
        return response($room->load('roomType'), Response::HTTP_OK);
    }

    public function update(Request $request, Room $room)
    {
        $validation = Validator::make($request->all(), [
            'room_type_id' => 'sometimes|exists:room_types,id',
            'number'       => 'nullable|string|max:50',
            'name'         => 'nullable|string|max:100',
            'capacity'     => 'sometimes|integer|min:1',
            'max_capacity' => 'nullable|integer|min:1',
            'room_price'   => 'sometimes|numeric|min:0',
            'status'       => 'nullable|string|max:50',
            'active'       => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $room->update($request->all());

        return response($room->fresh()->load('roomType'), Response::HTTP_OK);
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return response(['message' => 'Room deleted'], Response::HTTP_NO_CONTENT);
    }
}


