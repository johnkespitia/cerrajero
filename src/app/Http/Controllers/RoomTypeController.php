<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomTypeController extends Controller
{
    public function index()
    {
        $roomTypes = RoomType::orderBy('name')->get();
        return response($roomTypes, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name'             => 'required|string|max:100',
            'code'             => 'nullable|string|max:50|unique:room_types,code',
            'default_capacity' => 'required|integer|min:1',
            'max_capacity'     => 'nullable|integer|min:1',
            'base_price'       => 'required|numeric|min:0',
            'active'           => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomType = RoomType::create($request->all());

        return response($roomType, Response::HTTP_CREATED);
    }

    public function show(RoomType $roomType)
    {
        return response($roomType, Response::HTTP_OK);
    }

    public function update(Request $request, RoomType $roomType)
    {
        $validation = Validator::make($request->all(), [
            'name'             => 'sometimes|string|max:100',
            'code'             => 'nullable|string|max:50|unique:room_types,code,' . $roomType->id,
            'default_capacity' => 'sometimes|integer|min:1',
            'max_capacity'     => 'nullable|integer|min:1',
            'base_price'       => 'sometimes|numeric|min:0',
            'active'           => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomType->update($request->all());

        return response($roomType->fresh(), Response::HTTP_OK);
    }

    public function destroy(RoomType $roomType)
    {
        $roomType->delete();

        return response(['message' => 'Room type deleted'], Response::HTTP_NO_CONTENT);
    }
}



