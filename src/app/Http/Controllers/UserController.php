<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\User;
use \Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

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
}
