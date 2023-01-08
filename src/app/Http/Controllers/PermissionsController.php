<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:permissions|max:125',
            'guard_name' => 'required|max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $role = Permission::create([
            "name"=> $request->name,
            "guard_name"=> $request->guard_name
        ]);
        return response(['msg' => "Permission saved", 'rol'=>$role], Response::HTTP_OK);
    }

    public function list(Request $request){

        $permissions = Permission::all();
        return response($permissions, Response::HTTP_OK);
    }

    public function show(Permission $permission){
        return response($permission, Response::HTTP_OK);
    }

    public function update(Request $request,Permission $permission){

        $validation = Validator::make($request->all(), [
            'name' => 'unique:permissions|max:125',
            'guard_name' => 'max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $permission->update([
            'name' => $request->name??$permission->name,
            "guard_name"=> $request->guard_name??$permission->guard_name
        ]);
        return response(['msg' => "Permission saved", 'rol'=>$permission], Response::HTTP_OK);
    }
}
