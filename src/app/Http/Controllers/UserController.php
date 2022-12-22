<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\User;
use \Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function apiLogin(Request $request){
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response('unauthorized', Response::HTTP_UNAUTHORIZED)
                ->header('Content-Type', 'text/json');
        }
        $token = $user->createToken($user->email);
        return response(['token' => $token->plainTextToken], Response::HTTP_OK);
    }

    public function save(Request $request){
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|exists:rols,id',
            'active' => 'boolean'
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Crear un nuevo usuario
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->rol_id = $request->rol;
        $user->active = $request->active;
        $user->save();
        return response(['user' => $user], Response::HTTP_OK);
    }

    public function update(Request $request, User $user){
        $validation = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users',
            'password' => 'string|min:8|confirmed',
            'rol' => 'exists:rols,id',
            'active' => 'boolean'
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->name = $request->name??$user->name;
        $user->email = $request->email??$user->email;
        $user->password = bcrypt($request->password)??$user->password;
        $user->rol_id = $request->rol??$user->rol_id;
        $user->active = $request->active??$user->active;
        $user->save();
        return response(['user' => $user], Response::HTTP_OK);
    }

    public function list(Request $request){

        $users = User::all();
        return response($users, Response::HTTP_OK);
    }

    public function show(User $user){
        return response($user, Response::HTTP_OK);
    }

    public function mydata(Request $request){
        return response($request->user(), Response::HTTP_OK);
    }
}
