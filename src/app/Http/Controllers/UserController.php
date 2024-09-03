<?php

namespace App\Http\Controllers;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    public function index()
    {
        return User::with("rol")->get();
    }
 
    public function show(User $user)
    {
        return $user;
    }

    public function store(Request $request)
    {
        $user =  User::create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password')),
            "rol_id" =>  $request->get('rol_id')
        ]);
       
        $user = User::with("rol")->find($user->id);
        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->all());
        return response()->json($user, 200);
    }

    public function delete(Request $request, User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }
}
