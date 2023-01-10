<?php

namespace App\Http\Controllers;

use App\Models\Guard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class GuardController extends Controller
{
    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:guards|max:125',
            'driver' => 'sometimes|max:125',
            'provider' => 'sometimes|max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $guard = Guard::create([
            "name"=> $request->name,
            "driver"=> $request->driver??"sanctum",
            "provider"=> $request->provider??"users",
        ]);
        return response(['msg' => "Guard saved", 'guard'=>$guard], Response::HTTP_OK);
    }

    public function list(Request $request){

        $guards = Guard::all();
        return response($guards, Response::HTTP_OK);
    }

    public function show(Guard $guard){
        return response($guard, Response::HTTP_OK);
    }

    public function update(Request $request,Guard $guard){

        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|unique:guards|max:125',
            'driver' => 'sometimes|max:125',
            'provider' => 'sometimes|max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $guard->update([
            'name' => $request->name??$guard->name,
            "driver"=> $request->driver??$guard->driver
        ]);
        return response(['msg' => "Guard saved", 'guard'=>$guard], Response::HTTP_OK);
    }

}
