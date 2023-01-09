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
        Route::get('/rol', 'list')->middleware('permission:rol.edit,cerrajero');
        Route::get('/rol/{rol}', 'show')->middleware('permission:rol.edit,cerrajero');
        Route::post('/rol', 'save')->middleware('permission:rol.edit,cerrajero');
        Route::put('/rol/{rol}', 'update')->middleware('permission:rol.edit,cerrajero');
        Route::post('/rol/grant-permission/{rol}', 'grantPermission')->middleware('permission:rol.grantpermission,cerrajero');
        Route::post('/rol/revoke-permission/{rol}', 'revokePermission')->middleware('permission:rol.revokepermission,cerrajero');
    });
    Route::controller(\App\Http\Controllers\PermissionsController::class)->group(function () {
        Route::get('/permission', 'list')->middleware('permission:permission.edit,cerrajero');
        Route::get('/permission/{permission}', 'show')->middleware('permission:permission.edit,cerrajero');
        Route::post('/permission', 'save')->middleware('permission:permission.edit,cerrajero');
        Route::put('/permission/{permission}', 'update')->middleware('permission:permission.edit,cerrajero');
    });
    Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
        Route::get('/accounts', 'list')->middleware('permission:user.list,cerrajero');
        Route::get('/accounts/{user}', 'show')->middleware('permission:user.list,cerrajero');
        Route::post('/accounts', 'save')->middleware('permission:user.create,cerrajero');
        Route::put('/accounts/{user}', 'update')->middleware('permission:user.edit,cerrajero');
        Route::get('/my-account', 'mydata');
    });
});
