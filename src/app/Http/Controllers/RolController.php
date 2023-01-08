<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;


class RolController extends Controller
{
    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:roles|max:125',
            'guard_name' => 'required|max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $role = Role::create([
            "name"=> $request->name,
            "guard_name"=> $request->guard_name
        ]);
        return response(['msg' => "Rol saved", 'rol'=>$role], Response::HTTP_OK);
    }

    public function list(Request $request){

        $rols = Role::all();
        return response($rols, Response::HTTP_OK);
    }

    public function show(Role $rol){
        return response($rol, Response::HTTP_OK);
    }

    public function update(Request $request,Role $rol){

        $validation = Validator::make($request->all(), [
            'name' => 'unique:roles|max:125',
            'guard_name' => 'max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $rol->update([
            'name' => $request->name??$rol->name,
            "guard_name"=> $request->guard_name??$rol->guard_name
        ]);
        return response(['msg' => "Rol saved", 'rol'=>$rol], Response::HTTP_OK);
    }

}