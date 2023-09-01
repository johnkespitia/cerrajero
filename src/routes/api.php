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

Route::get("/greeting", function() {
    return "HELLO WORLD!!";
});

Route::post('/login', [\App\Http\Controllers\UserController::class, 'apiLogin']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::controller(\App\Http\Controllers\RolController::class)->group(function () {
        Route::get('/rol', 'list')->middleware('permission:rol.edit,user_manager');
        Route::get('/rol/{rol}', 'show')->middleware('permission:rol.edit,user_manager');
        Route::post('/rol', 'save')->middleware('permission:rol.edit,user_manager');
        Route::put('/rol/{rol}', 'update')->middleware('permission:rol.edit,user_manager');
        Route::post('/rol/grant-permission/{rol}', 'grantPermission')->middleware('permission:rol.grantpermission,user_manager');
        Route::post('/rol/revoke-permission/{rol}', 'revokePermission')->middleware('permission:rol.revokepermission,user_manager');
    });
    Route::controller(\App\Http\Controllers\PermissionsController::class)->group(function () {
        Route::get('/permission', 'list')->middleware('permission:permission.edit,user_manager');
        Route::get('/permission/{permission}', 'show')->middleware('permission:permission.edit,user_manager');
        Route::post('/permission', 'save')->middleware('permission:permission.edit,user_manager');
        Route::put('/permission/{permission}', 'update')->middleware('permission:permission.edit,user_manager');
    });
    Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
        Route::get('/accounts', 'list')->middleware('permission:user.list,user_manager');
        Route::get('/accounts/{user}', 'show')->middleware('permission:user.list,user_manager');
        Route::post('/accounts', 'save')->middleware('permission:user.create,user_manager');
        Route::put('/accounts/{user}', 'update')->middleware('permission:user.edit,user_manager');
        Route::post('/accounts/role/{user}', 'assignRole')->middleware('permission:user.edit,user_manager');
        Route::delete('/accounts/role/{user}/{rol}', 'removeRole')->middleware('permission:user.edit,user_manager');
        Route::post('/accounts/superior/{user}', 'assignSuperior')->middleware('permission:user.edit,user_manager');
        Route::delete('/accounts/superior/{user}/{superior}', 'removeSuperior')->middleware('permission:user.edit,user_manager');
        Route::get('/my-account', 'mydata');
        Route::get('/can-i/{guard}/{permission}', 'cani');
    });
    Route::controller(\App\Http\Controllers\GuardController::class)->group(function () {
        Route::get('/guard', 'list')->middleware('permission:guard.list,user_manager');
        Route::get('/guard/{guard}', 'show')->middleware('permission:guard.list,user_manager');
        Route::post('/guard', 'save')->middleware('permission:guard.create,user_manager');
        Route::put('/guard/{guard}', 'update')->middleware('permission:guard.edit,user_manager');
    });

    // BODEGUERO

    Route::controller(\App\Http\Controllers\InventoryCategoryController::class)->group(function () {
        Route::get('/inventory-categories', 'index')->middleware('permission:category.list,inventory_manager');
        Route::get('/inventory-categories/{inventoryCategory}', 'show')->middleware('permission:category.list,inventory_manager');
        Route::post('/inventory-categories', 'store')->middleware('permission:category.create,inventory_manager');
        Route::put('/inventory-categories/{inventoryCategory}', 'update')->middleware('permission:category.edit,inventory_manager');
    });
    Route::controller(\App\Http\Controllers\InventoryTypeInputController::class)->group(function () {
        Route::get('/inventory-type-input', 'index')->middleware('permission:inventory-type.list,inventory_manager');
        Route::get('/inventory-type-input/{inventoryTypeInput}', 'show')->middleware('permission:inventory-type.list,inventory_manager');
        Route::post('/inventory-type-input', 'store')->middleware('permission:inventory-type.create,inventory_manager');
        Route::put('/inventory-type-input/{inventoryTypeInput}', 'update')->middleware('permission:inventory-type.edit,inventory_manager');
    });

    Route::controller(\App\Http\Controllers\InventoryInputController::class)->group(function () {
        Route::get('/inventory-input', 'index')->middleware('permission:input.list,inventory_manager');
        Route::get('/inventory-input/{inventoryInput}', 'show')->middleware('permission:input.list,inventory_manager');
        Route::post('/inventory-input', 'store')->middleware('permission:input.create,inventory_manager');
        Route::put('/inventory-input/{inventoryInput}', 'update')->middleware('permission:input.edit,inventory_manager');
    });

    Route::controller(\App\Http\Controllers\InventoryBatchController::class)->group(function () {
        Route::get('/inventory-batch', 'index')->middleware('permission:batch.list,inventory_manager');
        Route::get('/inventory-batch/{inventoryBatch}', 'show')->middleware('permission:batch.list,inventory_manager');
        Route::post('/inventory-batch', 'store')->middleware('permission:batch.create,inventory_manager');
        Route::put('/inventory-batch/{inventoryBatch}', 'update')->middleware('permission:batch.edit,inventory_manager');
    });

    Route::controller(\App\Http\Controllers\InventoryMeasureController::class)->group(function () {
        Route::get('/inventory-measures', 'index')->middleware('permission:measures.list,inventory_manager');
        Route::get('/inventory-measures/{inventoryMeasure}', 'show')->middleware('permission:measures.list,inventory_manager');
        Route::post('/inventory-measures', 'store')->middleware('permission:measures.create,inventory_manager');
        Route::put('/inventory-measures/{inventoryMeasure}', 'update')->middleware('permission:measures.edit,inventory_manager');
        Route::get('/inventory-measures/conversion/{measureId}', 'show')->middleware('permission:measures.list,inventory_manager');
        Route::post('/inventory-measures/conversion', 'storeConversion')->middleware('permission:measures.create,inventory_manager');
        Route::put('/inventory-measures/conversion/{conversion}', 'updateConversion')->middleware('permission:measures.edit,inventory_manager');
        Route::get('/convert-measures', 'convert');
        Route::get('/convert-measure-list', 'conversions');

    });

    Route::controller(\App\Http\Controllers\InventoryPackageController::class)->group(function () {
        Route::get('/inventory-package', 'list')->middleware('permission:package.list,inventory_manager');
        Route::get('/inventory-package/{package}', 'show')->middleware('permission:package.list,inventory_manager');
        Route::post('/inventory-package', 'save')->middleware('permission:package.create,inventory_manager');
        Route::put('/inventory-package/{package}', 'update')->middleware('permission:package.edit,inventory_manager');
    });
    Route::controller(\App\Http\Controllers\InventoryPackageSupplyController::class)->group(function () {
        Route::get('/inventory-package-supply', 'list')->middleware('permission:package.list,inventory_manager');
        Route::get('/inventory-package-supply/{package}', 'show')->middleware('permission:package.list,inventory_manager');
        Route::post('/inventory-package-supply', 'save')->middleware('permission:package.create,inventory_manager');
        Route::put('/inventory-package-supply/{package}', 'update')->middleware('permission:package.edit,inventory_manager');
    });
    Route::controller(\App\Http\Controllers\InventoryPackageConsumeController::class)->group(function () {
        Route::get('/inventory-package-consume', 'list')->middleware('permission:package.list,inventory_manager');
        Route::get('/inventory-package-consume/{package}', 'show')->middleware('permission:package.list,inventory_manager');
        Route::post('/inventory-package-consume', 'save')->middleware('permission:package.create,inventory_manager');
        Route::put('/inventory-package-consume/{package}', 'update')->middleware('permission:package.edit,inventory_manager');
    });


    Route::controller(\App\Http\Controllers\KitchenRecipeController::class)->group(function () {
        Route::get('/kitchen-recipes', 'index')->middleware('permission:recipes.list,formula_manager');
        Route::get('/kitchen-recipes/{recipe}', 'show')->middleware('permission:recipes.list,formula_manager');
        Route::post('/kitchen-recipes', 'store')->middleware('permission:recipes.create,formula_manager');
        Route::put('/kitchen-recipes/{recipe}', 'update')->middleware('permission:recipes.edit,formula_manager');
    });

    Route::controller(\App\Http\Controllers\RecipeStepController::class)->group(function () {
        Route::post('/kitchen-recipe-steps', 'store')->middleware('permission:recipes.create,formula_manager');
        Route::put('/kitchen-recipe-steps/{recipeStep}', 'update')->middleware('permission:recipes.edit,formula_manager');
        Route::delete('/kitchen-recipe-steps/{recipeStep}', 'destroy')->middleware('permission:recipes.edit,formula_manager');
    });

    Route::controller(\App\Http\Controllers\RecipeIngredientController::class)->group(function () {
        Route::post('/kitchen-recipes-ingredient', 'store')->middleware('permission:recipes.create,formula_manager');
        Route::put('/kitchen-recipes-ingredient/{recipeIngredient}', 'update')->middleware('permission:recipes.edit,formula_manager');
        Route::delete('/kitchen-recipes-ingredient/{recipeIngredient}', 'destroy')->middleware('permission:recipes.edit,formula_manager');
    });


    Route::controller(\App\Http\Controllers\OrderController::class)->group(function () {
        Route::get('/order', 'index')->middleware('permission:order.list,factory_manager');
        Route::get('/order/{order}', 'show')->middleware('permission:order.list,factory_manager');
        Route::post('/order', 'store')->middleware('permission:order.create,factory_manager');
        Route::put('/order/{order}', 'update')->middleware('permission:order.edit,factory_manager');
        Route::delete('/order/{order}', 'destroy')->middleware('permission:order.edit,factory_manager');
    });
    Route::controller(\App\Http\Controllers\OrderItemController::class)->group(function () {
        Route::post('/order-item', 'store')->middleware('permission:order.create,factory_manager');
        Route::put('/order-item/{orderItem}', 'update')->middleware('permission:order.edit,factory_manager');
        Route::delete('/order-item/{orderItem}', 'destroy')->middleware('permission:order.edit,factory_manager');
    });
    Route::controller(\App\Http\Controllers\ConsumedInputItemController::class)->group(function () {
        Route::post('/order-item-consume', 'store')->middleware('permission:order.create,factory_manager');
        Route::put('/order-item-consume/{consumedInputItem}', 'update')->middleware('permission:order.edit,factory_manager');
    });

    Route::controller(\App\Http\Controllers\ProducedBatchController::class)->group(function () {
        Route::get('/order-item-batch', 'index')->middleware('permission:order.create,factory_manager');
        Route::get('/order-item-batch/{producedBatch}', 'show')->middleware('permission:order.create,factory_manager');
        Route::put('/order-item-batch/{producedBatch}', 'update')->middleware('permission:order.edit,factory_manager');
    });

    Route::controller(\App\Http\Controllers\ProductionNotesController::class)->group(function () {
        Route::post('/order-item-notes', 'save')->middleware('permission:order.list,factory_manager');
    });
});
