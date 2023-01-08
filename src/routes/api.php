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
        Route::post('/rol/grant-permission/{rol}', 'grantPermission');
        Route::post('/rol/revoke-permission/{rol}', 'revokePermission');
    });
    Route::controller(\App\Http\Controllers\PermissionsController::class)->group(function () {
        Route::get('/permission', 'list');
        Route::get('/permission/{permission}', 'show');
        Route::post('/permission', 'save');
        Route::put('/permission/{permission}', 'update');
    });
    Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
        Route::get('/accounts', 'list');
        Route::get('/accounts/{user}', 'show');
        Route::post('/accounts', 'save');
        Route::put('/accounts/{user}', 'update');
        Route::get('/my-account', 'mydata');
    });
});
