<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get("/greating", function() {
    return "HELLO WORLD!!";
});

Route::post('/login', [\App\Http\Controllers\UserController::class, 'apiLogin']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::controller(\App\Http\Controllers\RolController::class)->group(function () {
        Route::get('/rol', 'list');
        Route::get('/rol/{rol}', 'show');
        Route::post('/rol', 'save');
        Route::put('/rol/{rol}', 'update');
    });
});
