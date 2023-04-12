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
        Route::post('/accounts/role/{user}', 'assignRole')->middleware('permission:user.edit,cerrajero');
        Route::delete('/accounts/role/{user}/{rol}', 'removeRole')->middleware('permission:user.edit,cerrajero');
        Route::post('/accounts/superior/{user}', 'assignSuperior')->middleware('permission:user.edit,cerrajero');
        Route::delete('/accounts/superior/{user}/{superior}', 'removeSuperior')->middleware('permission:user.edit,cerrajero');
        Route::get('/my-account', 'mydata');
        Route::get('/can-i/{guard}/{permission}', 'cani');
    });
    Route::controller(\App\Http\Controllers\GuardController::class)->group(function () {
        Route::get('/guard', 'list')->middleware('permission:guard.list,cerrajero');
        Route::get('/guard/{guard}', 'show')->middleware('permission:guard.list,cerrajero');
        Route::post('/guard', 'save')->middleware('permission:guard.create,cerrajero');
        Route::put('/guard/{guard}', 'update')->middleware('permission:guard.edit,cerrajero');
    });

    // BODEGUERO

    Route::controller(\App\Http\Controllers\InventoryCategoryController::class)->group(function () {
        Route::get('/inventory-categories', 'index')->middleware('permission:category.list,bodeguero');
        Route::get('/inventory-categories/{inventoryCategory}', 'show')->middleware('permission:category.list,bodeguero');
        Route::post('/inventory-categories', 'store')->middleware('permission:category.create,bodeguero');
        Route::put('/inventory-categories/{inventoryCategory}', 'update')->middleware('permission:category.edit,bodeguero');
    });
    Route::controller(\App\Http\Controllers\InventoryTypeInputController::class)->group(function () {
        Route::get('/inventory-type-input', 'index')->middleware('permission:inventory-type.list,bodeguero');
        Route::get('/inventory-type-input/{inventoryTypeInput}', 'show')->middleware('permission:inventory-type.list,bodeguero');
        Route::post('/inventory-type-input', 'store')->middleware('permission:inventory-type.create,bodeguero');
        Route::put('/inventory-type-input/{inventoryTypeInput}', 'update')->middleware('permission:inventory-type.edit,bodeguero');
    });

    Route::controller(\App\Http\Controllers\InventoryInputController::class)->group(function () {
        Route::get('/inventory-input', 'index')->middleware('permission:input.list,bodeguero');
        Route::get('/inventory-input/{inventoryInput}', 'show')->middleware('permission:input.list,bodeguero');
        Route::post('/inventory-input', 'store')->middleware('permission:input.create,bodeguero');
        Route::put('/inventory-input/{inventoryInput}', 'update')->middleware('permission:input.edit,bodeguero');
    });

    Route::controller(\App\Http\Controllers\InventoryBatchController::class)->group(function () {
        Route::get('/inventory-batch', 'index')->middleware('permission:batch.list,bodeguero');
        Route::get('/inventory-batch/{inventoryBatch}', 'show')->middleware('permission:batch.list,bodeguero');
        Route::post('/inventory-batch', 'store')->middleware('permission:batch.create,bodeguero');
        Route::put('/inventory-batch/{inventoryBatch}', 'update')->middleware('permission:batch.edit,bodeguero');
    });

    Route::controller(\App\Http\Controllers\InventoryMeasureController::class)->group(function () {
        Route::get('/inventory-measures', 'index')->middleware('permission:measures.list,bodeguero');
        Route::get('/inventory-measures/{inventoryMeasure}', 'show')->middleware('permission:measures.list,bodeguero');
        Route::post('/inventory-measures', 'store')->middleware('permission:measures.create,bodeguero');
        Route::put('/inventory-measures/{inventoryMeasure}', 'update')->middleware('permission:measures.edit,bodeguero');
        Route::get('/inventory-measures/conversion/{measureId}', 'show')->middleware('permission:measures.list,bodeguero');
        Route::post('/inventory-measures/conversion', 'storeConversion')->middleware('permission:measures.create,bodeguero');
        Route::put('/inventory-measures/conversion/{inventoryMeasure}', 'updateConversion')->middleware('permission:measures.edit,bodeguero');
        Route::get('/convert-measures', 'convert');

    });

    Route::controller(\App\Http\Controllers\KitchenRecipeController::class)->group(function () {
        Route::get('/kitchen-recipes', 'index')->middleware('permission:recipes.list,cocinero');
        Route::get('/kitchen-recipes/{recipe}', 'show')->middleware('permission:recipes.list,cocinero');
        Route::post('/kitchen-recipes', 'store')->middleware('permission:recipes.create,cocinero');
        Route::put('/kitchen-recipes/{recipe}', 'update')->middleware('permission:recipes.edit,cocinero');
    });

    Route::controller(\App\Http\Controllers\RecipeStepController::class)->group(function () {
        Route::post('/kitchen-recipe-steps', 'store')->middleware('permission:recipes.create,cocinero');
        Route::put('/kitchen-recipe-steps/{recipeStep}', 'update')->middleware('permission:recipes.edit,cocinero');
        Route::delete('/kitchen-recipe-steps/{recipeStep}', 'destroy')->middleware('permission:recipes.edit,cocinero');
    });

    Route::controller(\App\Http\Controllers\RecipeIngredientController::class)->group(function () {
        Route::post('/kitchen-recipes-ingredient', 'store')->middleware('permission:recipes.create,cocinero');
        Route::put('/kitchen-recipes-ingredient/{recipeIngredient}', 'update')->middleware('permission:recipes.edit,cocinero');
        Route::delete('/kitchen-recipes-ingredient/{recipeIngredient}', 'destroy')->middleware('permission:recipes.edit,cocinero');
    });


    Route::controller(\App\Http\Controllers\OrderController::class)->group(function () {
        Route::get('/order', 'index')->middleware('permission:order.list,cocinero');
        Route::get('/order/{order}', 'show')->middleware('permission:order.list,cocinero');
        Route::post('/order', 'store')->middleware('permission:order.create,cocinero');
        Route::put('/order/{order}', 'update')->middleware('permission:order.edit,cocinero');
        Route::delete('/order/{order}', 'destroy')->middleware('permission:order.edit,cocinero');
    });
    Route::controller(\App\Http\Controllers\OrderItemController::class)->group(function () {
        Route::post('/order-item', 'store')->middleware('permission:order.create,cocinero');
        Route::put('/order-item/{orderItem}', 'update')->middleware('permission:order.edit,cocinero');
        Route::delete('/order-item/{orderItem}', 'destroy')->middleware('permission:order.edit,cocinero');
    });

    Route::controller(\App\Http\Controllers\ProducedBatchController::class)->group(function () {
        Route::get('/order-item-batch', 'index')->middleware('permission:order.create,cocinero');
        Route::get('/order-item-batch/{producedBatch}', 'show')->middleware('permission:order.create,cocinero');
        Route::put('/order-item-batch/{producedBatch}', 'update')->middleware('permission:order.edit,cocinero');
    });
});
