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
        Route::get('/rol', 'list')->middleware('permission:rol.listar,usuarios_admin');
        Route::get('/rol/{rol}', 'show')->middleware('permission:rol.listar,usuarios_admin');
        Route::post('/rol', 'save')->middleware('permission:rol.crear,usuarios_admin');
        Route::put('/rol/{rol}', 'update')->middleware('permission:rol.editar,usuarios_admin');
        Route::post('/rol/grant-permission/{rol}', 'grantPermission')->middleware('permission:rol.darpermiso,usuarios_admin');
        Route::post('/rol/revoke-permission/{rol}', 'revokePermission')->middleware('permission:rol.quitarpermiso,usuarios_admin');
    });
    Route::controller(\App\Http\Controllers\PermissionsController::class)->group(function () {
        Route::get('/permission', 'list')->middleware('permission:permisos.listar,usuarios_admin');
        Route::get('/permission/{permission}', 'show')->middleware('permission:permisos.listar,usuarios_admin');
        Route::post('/permission', 'save')->middleware('permission:permisos.crear,usuarios_admin');
        Route::put('/permission/{permission}', 'update')->middleware('permission:permisos.editar,usuarios_admin');
    });
    Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
        Route::get('/accounts', 'list')->middleware('permission:usuario.listar,usuarios_admin');
        Route::get('/accounts/{user}', 'show')->middleware('permission:usuario.listar,usuarios_admin');
        Route::post('/accounts', 'save')->middleware('permission:usuario.crear,usuarios_admin');
        Route::put('/accounts/{user}', 'update')->middleware('permission:usuario.editar,usuarios_admin');
        Route::post('/accounts/role/{user}', 'assignRole')->middleware('permission:usuario.editar,usuarios_admin');
        Route::delete('/accounts/role/{user}/{rol}', 'removeRole')->middleware('permission:usuario.editar,usuarios_admin');
        Route::post('/accounts/superior/{user}', 'assignSuperior')->middleware('permission:usuario.editar,usuarios_admin');
        Route::delete('/accounts/superior/{user}/{superior}', 'removeSuperior')->middleware('permission:usuario.editar,usuarios_admin');
        Route::get('/my-account', 'mydata');
        Route::get('/can-i/{guard}/{permission}', 'cani');
    });
    Route::controller(\App\Http\Controllers\GuardController::class)->group(function () {
        Route::get('/guard', 'list')->middleware('permission:modulo.listar,usuarios_admin');
        Route::get('/guard/{guard}', 'show')->middleware('permission:modulo.listar,usuarios_admin');
        Route::post('/guard', 'save')->middleware('permission:modulo.crear,usuarios_admin');
        Route::put('/guard/{guard}', 'update')->middleware('permission:modulo.editar,usuarios_admin');
    });

    // BODEGUERO

    Route::controller(\App\Http\Controllers\InventoryCategoryController::class)->group(function () {
        Route::get('/inventory-categories', 'index')->middleware('permission:categoria.listar,administrador_inventario');
        Route::get('/inventory-categories/{inventoryCategory}', 'show')->middleware('permission:categoria.listar,administrador_inventario');
        Route::post('/inventory-categories', 'store')->middleware('permission:categoria.crear,administrador_inventario');
        Route::put('/inventory-categories/{inventoryCategory}', 'update')->middleware('permission:categoria.editar,administrador_inventario');
    });
    Route::controller(\App\Http\Controllers\InventoryTypeInputController::class)->group(function () {
        Route::get('/inventory-type-input', 'index')->middleware('permission:tipo-inventario.listar,administrador_inventario');
        Route::get('/inventory-type-input/{inventoryTypeInput}', 'show')->middleware('permission:tipo-inventario.listar,administrador_inventario');
        Route::post('/inventory-type-input', 'store')->middleware('permission:tipo-inventario.crear,administrador_inventario');
        Route::put('/inventory-type-input/{inventoryTypeInput}', 'update')->middleware('permission:tipo-inventario.editar,administrador_inventario');
    });

    Route::controller(\App\Http\Controllers\InventoryInputController::class)->group(function () {
        Route::get('/inventory-input', 'index')->middleware('permission:insumo.listar,administrador_inventario');
        Route::get('/inventory-input/{inventoryInput}', 'show')->middleware('permission:insumo.listar,administrador_inventario');
        Route::post('/inventory-input', 'store')->middleware('permission:insumo.crear,administrador_inventario');
        Route::put('/inventory-input/{inventoryInput}', 'update')->middleware('permission:insumo.editar,administrador_inventario');
    });

    Route::controller(\App\Http\Controllers\InventoryBatchController::class)->group(function () {
        Route::get('/inventory-batch', 'index')->middleware('permission:lote.listar,administrador_inventario');
        Route::get('/inventory-batch/{inventoryBatch}', 'show')->middleware('permission:lote.listar,administrador_inventario');
        Route::post('/inventory-batch', 'store')->middleware('permission:lote.crear,administrador_inventario');
        Route::put('/inventory-batch/{inventoryBatch}', 'update')->middleware('permission:lote.editar,administrador_inventario');
    });

    Route::controller(\App\Http\Controllers\InventoryMeasureController::class)->group(function () {
        Route::get('/inventory-measures', 'index')->middleware('permission:medidas.listar,administrador_inventario');
        Route::get('/inventory-measures/{inventoryMeasure}', 'show')->middleware('permission:medidas.listar,administrador_inventario');
        Route::post('/inventory-measures', 'store')->middleware('permission:medidas.crear,administrador_inventario');
        Route::put('/inventory-measures/{inventoryMeasure}', 'update')->middleware('permission:medidas.editar,administrador_inventario');
        Route::get('/inventory-measures/conversion/{measureId}', 'show')->middleware('permission:medidas.listar,administrador_inventario');
        Route::post('/inventory-measures/conversion', 'storeConversion')->middleware('permission:medidas.crear,administrador_inventario');
        Route::put('/inventory-measures/conversion/{conversion}', 'updateConversion')->middleware('permission:medidas.editar,administrador_inventario');
        Route::get('/convert-measures', 'convert');
        Route::get('/convert-measure-list', 'conversions');

    });

    Route::controller(\App\Http\Controllers\InventoryPackageController::class)->group(function () {
        Route::get('/inventory-package', 'list')->middleware('permission:empaque.listar,administrador_inventario');
        Route::get('/inventory-package/{package}', 'show')->middleware('permission:empaque.listar,administrador_inventario');
        Route::post('/inventory-package', 'save')->middleware('permission:empaque.crear,administrador_inventario');
        Route::put('/inventory-package/{package}', 'update')->middleware('permission:empaque.editar,administrador_inventario');
    });
    Route::controller(\App\Http\Controllers\InventoryPackageSupplyController::class)->group(function () {
        Route::get('/inventory-package-supply', 'list')->middleware('permission:empaque.listar,administrador_inventario');
        Route::get('/inventory-package-supply/{package}', 'show')->middleware('permission:empaque.listar,administrador_inventario');
        Route::post('/inventory-package-supply', 'save')->middleware('permission:empaque.crear,administrador_inventario');
        Route::put('/inventory-package-supply/{package}', 'update')->middleware('permission:empaque.editar,administrador_inventario');
    });
    Route::controller(\App\Http\Controllers\InventoryPackageConsumeController::class)->group(function () {
        Route::get('/inventory-package-consume', 'list')->middleware('permission:empaque.listar,administrador_inventario');
        Route::get('/inventory-package-consume/{package}', 'show')->middleware('permission:empaque.listar,administrador_inventario');
        Route::post('/inventory-package-consume', 'save')->middleware('permission:empaque.crear,administrador_inventario');
        Route::put('/inventory-package-consume/{package}', 'update')->middleware('permission:empaque.editar,administrador_inventario');
    });


    Route::controller(\App\Http\Controllers\KitchenRecipeController::class)->group(function () {
        Route::get('/kitchen-recipes', 'index')->middleware('permission:formulas.listar,administrador_formulas');
        Route::get('/kitchen-recipes/{recipe}', 'show')->middleware('permission:formulas.listar,administrador_formulas');
        Route::post('/kitchen-recipes', 'store')->middleware('permission:formulas.crear,administrador_formulas');
        Route::put('/kitchen-recipes/{recipe}', 'update')->middleware('permission:formulas.editar,administrador_formulas');
    });

    Route::controller(\App\Http\Controllers\RecipeStepController::class)->group(function () {
        Route::post('/kitchen-recipe-steps', 'store')->middleware('permission:formulas.crear,administrador_formulas');
        Route::put('/kitchen-recipe-steps/{recipeStep}', 'update')->middleware('permission:formulas.editar,administrador_formulas');
        Route::delete('/kitchen-recipe-steps/{recipeStep}', 'destroy')->middleware('permission:formulas.editar,administrador_formulas');
    });

    Route::controller(\App\Http\Controllers\RecipeIngredientController::class)->group(function () {
        Route::post('/kitchen-recipes-ingredient', 'store')->middleware('permission:formulas.crear,administrador_formulas');
        Route::put('/kitchen-recipes-ingredient/{recipeIngredient}', 'update')->middleware('permission:formulas.editar,administrador_formulas');
        Route::delete('/kitchen-recipes-ingredient/{recipeIngredient}', 'destroy')->middleware('permission:formulas.editar,administrador_formulas');
    });


    Route::controller(\App\Http\Controllers\OrderController::class)->group(function () {
        Route::get('/order', 'index')->middleware('permission:orden-produccion.listar,administrador_produccion');
        Route::get('/order/{order}', 'show')->middleware('permission:orden-produccion.listar,administrador_produccion');
        Route::post('/order', 'store')->middleware('permission:orden-produccion.crear,administrador_produccion');
        Route::put('/order/{order}', 'update')->middleware('permission:orden-produccion.editar,administrador_produccion');
        Route::delete('/order/{order}', 'destroy')->middleware('permission:orden-produccion.editar,administrador_produccion');
    });
    Route::controller(\App\Http\Controllers\OrderItemController::class)->group(function () {
        Route::post('/order-item', 'store')->middleware('permission:orden-produccion.crear,administrador_produccion');
        Route::put('/order-item/{orderItem}', 'update')->middleware('permission:orden-produccion.editar,administrador_produccion');
        Route::delete('/order-item/{orderItem}', 'destroy')->middleware('permission:orden-produccion.editar,administrador_produccion');
    });
    Route::controller(\App\Http\Controllers\ConsumedInputItemController::class)->group(function () {
        Route::post('/order-item-consume', 'store')->middleware('permission:orden-produccion.crear,administrador_produccion');
        Route::put('/order-item-consume/{consumedInputItem}', 'update')->middleware('permission:orden-produccion.editar,administrador_produccion');
    });

    Route::controller(\App\Http\Controllers\ProducedBatchController::class)->group(function () {
        Route::get('/order-item-batch', 'index')->middleware('permission:orden-produccion.crear,administrador_produccion');
        Route::get('/order-item-batch/{producedBatch}', 'show')->middleware('permission:orden-produccion.crear,administrador_produccion');
        Route::put('/order-item-batch/{producedBatch}', 'update')->middleware('permission:orden-produccion.editar,administrador_produccion');
    });

    Route::controller(\App\Http\Controllers\ProductionNotesController::class)->group(function () {
        Route::post('/order-item-notes', 'save')->middleware('permission:orden-produccion.listar,administrador_produccion');
    });
});
