<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;


class RolController extends Controller
{
    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:rols|max:20',
            'status' => 'required|boolean',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response("Verify the input data", Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $model = new Rol;
        $model->name = $request->name;
        $model->status = $request->status;
        $model->save();
        return response(['msg' => "Rol saved", 'rol'=>$model], Response::HTTP_OK);
    }

    public function list(Request $request){

        $rols = Rol::all();
        return response($rols, Response::HTTP_OK);
    }

    public function show(Rol $rol){
        return response($rol, Response::HTTP_OK);
    }

    public function update(Request $request,Rol $rol){

        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:rols|max:20',
            'status' => 'required|boolean',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $rol->name = $request->name;
        $rol->status = $request->status;
        $rol->save();
        return response(['msg' => "Rol saved", 'rol'=>$rol], Response::HTTP_OK);
    }

}
