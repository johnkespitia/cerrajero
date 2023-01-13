<?php

namespace App\Http\Controllers;

use App\Models\Guard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="My API",
 *      description="API for managing users and roles",
 *      @OA\Contact(
 *          email="your@email.com"
 *      ),
 *     )
 * * @OA\PathItem(
 *     path="/guards",
 *     @OA\Get(
 *         summary="Get all users",
 *         operationId="list",
 *         @OA\Response(
 *             response="200",
 *             description="Success",
 *             @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/User"))
 *         ),
 *         @OA\Response(
 *             response="400",
 *             description="Bad Request"
 *         )
 *     ),
 *  )
 */
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
