<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\User;
use \Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Class UserController
 *
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    /**
     * Login user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiLogin(Request $request){
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response('unauthorized', Response::HTTP_UNAUTHORIZED)
                ->header('Content-Type', 'text/json');
        }
        $token = $user->createToken($user->email);
        return response(['token' => $token->plainTextToken], Response::HTTP_OK);
    }

    /**
     * Save a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function save(Request $request){
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'required|exists:roles,id',
            'active' => 'boolean',
            'superior' => 'exists:users,id'
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Crear un nuevo usuario
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->active = $request->active;
        $user->save();
        $rol = Role::find($request->rol);
        $user->assignRole($rol);
        if(!empty($request->superior)){
            $user->superior()->attach($request->superior);
        }
        return response(['user' => $user], Response::HTTP_OK);
    }

    /**
     * Update a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, User $user){
        $validation = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'string|min:8|confirmed',
            'rol' => 'sometimes|exists:roles,id',
            'active' => 'boolean',
            'superior' => 'sometimes|exists:users,id'
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->name = $request->name??$user->name;
        $user->email = $request->email??$user->email;
        $user->password = bcrypt($request->password)??$user->password;
        $user->active = $request->active??$user->active;
        $user->save();
        if(!empty($request->rol)){
            $user->assignRole(Role::find($request->rol));
        }
        if(!empty($request->superior)){
            $user->superior()->sync([$request->superior]);
        }
        return response(['user' => $user], Response::HTTP_OK);
    }

    /**
     * List all users
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request){

        $users = User::with("roles")->with("superior")->get();
        return response($users, Response::HTTP_OK);
    }

    /**
     * Show a user
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user){
        $roles = $user->roles;
        foreach ($roles as $r){
            $r->permissions;
        }
        $user->permissions;
        $user->superior;
        $user->dependency;
        return response($user, Response::HTTP_OK);
    }

    /**
     * Get user data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mydata(Request $request){
        $user = $request->user();
        $roles = $user->roles;
        foreach ($roles as $r){
            $r->permissions;
        }
        $user->permissions;
        $user->superior;
        $user->dependency;
        $user->professor;
        if($user->professor){
            $user->professor->skills;
        }
        $user->links;
        $user->student;
        return response($user, Response::HTTP_OK);
    }

    /**
     * Assign a role to a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assignRole(Request $request, User $user){
        $validation = Validator::make($request->all(), [
            'rol' => 'required|exists:roles,id',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $rol = Role::find($request->rol);
        $user->assignRole($rol);
        $user->roles;
        $user->superior;
        return response($user, Response::HTTP_OK);
    }

    /**
     * Assign a role to a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assignSuperior(Request $request, User $user){
        $validation = Validator::make($request->all(), [
            'superior' => 'required|exists:users,id',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->superior()->attach($request->superior);
        $user->roles;
        $user->superior;
        return response($user, Response::HTTP_OK);
    }

    /**
     * Remove a role from a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function removeRole(Request $request,User $user, $rol){
        $rol = Role::find($rol);
        $user->removeRole($rol);
        $user->roles;
        $user->superior;
        return response($user, Response::HTTP_OK);

    }
/**
     * Remove a role from a user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function removeSuperior(Request $request,User $user, $superior){
        $user->superior()->detach($superior);
        $user->roles;
        $user->superior;
        return response($user, Response::HTTP_OK);

    }

    /**
     * Check if user have permission
     *
     * @param Request $request
     * @param string $guard
     * @param string $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function cani(Request $request, $guard, $permission){
        if($request->user()->hasPermissionTo($permission, $guard)){
            return response(["message"=>"you can to do {$permission}"], Response::HTTP_OK);
        }
        return response(["message"=>"you can not to do {$permission}"], Response::HTTP_UNAUTHORIZED);
    }
}
