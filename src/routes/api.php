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

// Callback público de Google Calendar OAuth (sin autenticación)
Route::get('/google-calendar/callback', [\App\Http\Controllers\GoogleCalendarConfigController::class, 'handleCallback']);

Route::middleware(['auth:sanctum'])->group(function () {
    // ============================================
    // Módulo de Gestión de Usuarios y Permisos
    // ============================================
    
    // Rutas de Roles
    Route::controller(\App\Http\Controllers\RolController::class)->group(function () {
        Route::get('/rol', 'list')->middleware('permission:role.list,cerrajero');
        Route::get('/rol/{rol}', 'show')->middleware('permission:role.view,cerrajero');
        Route::post('/rol', 'save')->middleware('permission:role.create,cerrajero');
        Route::put('/rol/{rol}', 'update')->middleware('permission:role.edit,cerrajero');
        Route::post('/rol/grant-permission/{rol}', 'grantPermission')->middleware('permission:role.grant_permission,cerrajero');
        Route::post('/rol/revoke-permission/{rol}', 'revokePermission')->middleware('permission:role.revoke_permission,cerrajero');
    });

    // Rutas de Permisos
    Route::controller(\App\Http\Controllers\PermissionsController::class)->group(function () {
        Route::get('/permission', 'list')->middleware('permission:permission.list,cerrajero');
        Route::get('/permission/{permission}', 'show')->middleware('permission:permission.view,cerrajero');
        Route::post('/permission', 'save')->middleware('permission:permission.create,cerrajero');
        Route::put('/permission/{permission}', 'update')->middleware('permission:permission.edit,cerrajero');
    });

    // Rutas de Usuarios
    Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
        // Ruta básica para obtener usuarios (sin permisos específicos, solo autenticación)
        Route::get('/users', 'listBasic'); // Para selectores en formularios
        Route::get('/accounts', 'list')->middleware('permission:user.list,cerrajero');
        Route::get('/accounts/{user}', 'show')->middleware('permission:user.view,cerrajero');
        Route::get('/users/{user}', 'show')->middleware('permission:user.view,cerrajero'); // Alias para compatibilidad
        Route::post('/accounts', 'save')->middleware('permission:user.create,cerrajero');
        Route::put('/accounts/{user}', 'update')->middleware('permission:user.edit,cerrajero');
        Route::post('/accounts/role/{user}', 'assignRole')->middleware('permission:user.assign_role,cerrajero');
        Route::delete('/accounts/role/{user}/{rol}', 'removeRole')->middleware('permission:user.remove_role,cerrajero');
        Route::post('/accounts/superior/{user}', 'assignSuperior')->middleware('permission:user.assign_superior,cerrajero');
        Route::delete('/accounts/superior/{user}/{superior}', 'removeSuperior')->middleware('permission:user.remove_superior,cerrajero');
        Route::get('/my-account', 'mydata');
        Route::get('/can-i/{guard}/{permission}', 'cani');
    });

    // Rutas de Guards
    Route::controller(\App\Http\Controllers\GuardController::class)->group(function () {
        Route::get('/guard', 'list')->middleware('permission:guard.list,cerrajero');
        Route::get('/guard/{guard}', 'show')->middleware('permission:guard.view,cerrajero');
        Route::post('/guard', 'save')->middleware('permission:guard.create,cerrajero');
        Route::put('/guard/{guard}', 'update')->middleware('permission:guard.edit,cerrajero');
    });

    // restbodega

    Route::controller(\App\Http\Controllers\InventoryCategoryController::class)->group(function () {
        Route::get('/inventory-categories', 'index')->middleware('permission:category.list,restbodega');
        Route::get('/inventory-categories/{inventoryCategory}', 'show')->middleware('permission:category.list,restbodega');
        Route::post('/inventory-categories', 'store')->middleware('permission:category.create,restbodega');
        Route::put('/inventory-categories/{inventoryCategory}', 'update')->middleware('permission:category.edit,restbodega');
    });
    Route::controller(\App\Http\Controllers\InventoryTypeInputController::class)->group(function () {
        Route::get('/inventory-type-input', 'index')->middleware('permission:inventory-type.list,restbodega');
        Route::get('/inventory-type-input/{inventoryTypeInput}', 'show')->middleware('permission:inventory-type.list,restbodega');
        Route::post('/inventory-type-input', 'store')->middleware('permission:inventory-type.create,restbodega');
        Route::put('/inventory-type-input/{inventoryTypeInput}', 'update')->middleware('permission:inventory-type.edit,restbodega');
    });

    Route::controller(\App\Http\Controllers\InventoryInputController::class)->group(function () {
        Route::get('/inventory-input', 'index')->middleware('permission:input.list,restbodega');
        Route::get('/inventory-input/{inventoryInput}', 'show')->middleware('permission:input.list,restbodega');
        Route::post('/inventory-input', 'store')->middleware('permission:input.create,restbodega');
        Route::put('/inventory-input/{inventoryInput}', 'update')->middleware('permission:input.edit,restbodega');
    });

    Route::controller(\App\Http\Controllers\InventoryBatchController::class)->group(function () {
        Route::get('/inventory-batch', 'index')->middleware('permission:batch.list,restbodega');
        Route::get('/inventory-batch/{inventoryBatch}', 'show')->middleware('permission:batch.list,restbodega');
        Route::post('/inventory-batch', 'store')->middleware('permission:batch.create,restbodega');
        Route::put('/inventory-batch/{inventoryBatch}', 'update')->middleware('permission:batch.edit,restbodega');
    });

    Route::controller(\App\Http\Controllers\InventoryMeasureController::class)->group(function () {
        Route::get('/inventory-measures', 'index')->middleware('permission:measures.list,restbodega');
        Route::get('/inventory-measures/{inventoryMeasure}', 'show')->middleware('permission:measures.list,restbodega');
        Route::post('/inventory-measures', 'store')->middleware('permission:measures.create,restbodega');
        Route::put('/inventory-measures/{inventoryMeasure}', 'update')->middleware('permission:measures.edit,restbodega');
        Route::get('/inventory-measures/conversion/{measureId}', 'show')->middleware('permission:measures.list,restbodega');
        Route::post('/inventory-measures/conversion', 'storeConversion')->middleware('permission:measures.create,restbodega');
        Route::put('/inventory-measures/conversion/{conversion}', 'updateConversion')->middleware('permission:measures.edit,restbodega');
        Route::get('/convert-measures', 'convert');
        Route::get('/convert-measure-list', 'conversions');

    });

    Route::controller(\App\Http\Controllers\KitchenRecipeController::class)->group(function () {
        Route::get('/kitchen-recipes', 'index')->middleware('permission:recipes.list,restcocina');
        Route::get('/kitchen-recipes/{recipe}', 'show')->middleware('permission:recipes.list,restcocina');
        Route::post('/kitchen-recipes', 'store')->middleware('permission:recipes.create,restcocina');
        Route::put('/kitchen-recipes/{recipe}', 'update')->middleware('permission:recipes.edit,restcocina');
    });

    Route::controller(\App\Http\Controllers\RecipeStepController::class)->group(function () {
        Route::post('/kitchen-recipe-steps', 'store')->middleware('permission:recipes.create,restcocina');
        Route::put('/kitchen-recipe-steps/{recipeStep}', 'update')->middleware('permission:recipes.edit,restcocina');
        Route::delete('/kitchen-recipe-steps/{recipeStep}', 'destroy')->middleware('permission:recipes.edit,restcocina');
    });

    Route::controller(\App\Http\Controllers\RecipeIngredientController::class)->group(function () {
        Route::post('/kitchen-recipes-ingredient', 'store')->middleware('permission:recipes.create,restcocina');
        Route::put('/kitchen-recipes-ingredient/{recipeIngredient}', 'update')->middleware('permission:recipes.edit,restcocina');
        Route::delete('/kitchen-recipes-ingredient/{recipeIngredient}', 'destroy')->middleware('permission:recipes.edit,restcocina');
    });


    Route::controller(\App\Http\Controllers\OrderController::class)->group(function () {
        Route::get('/order', 'index')->middleware('permission:order.list,restcaja');
        Route::get('/order/{order}', 'show')->middleware('permission:order.list,restcaja');
        Route::post('/order', 'store')->middleware('permission:order.create,restcaja');
        Route::put('/order/{order}', 'update')->middleware('permission:order.edit,restcaja');
        Route::delete('/order/{order}', 'destroy')->middleware('permission:order.edit,restcaja');
        Route::get('/order/active-reservation', 'getActiveReservation')->middleware('permission:order.list,restcaja');
        Route::get('/order/reservation/{reservationId}/meal-consumption', 'getMealConsumption')->middleware('permission:order.list,restcaja');
        Route::post('/order/{order}/verify-inventory', 'verifyInventory')->middleware('permission:order.create,restcaja');
    });
    Route::controller(\App\Http\Controllers\OrderItemController::class)->group(function () {
        Route::post('/order-item', 'store')->middleware('permission:order.create,restcaja');
        Route::put('/order-item/{orderItem}', 'update')->middleware('permission:order.edit,restcaja');
        Route::delete('/order-item/{orderItem}', 'destroy')->middleware('permission:order.edit,restcaja');
        Route::post('/order-item/check-inventory', 'checkInventory')->middleware('permission:order.create,restcaja');
    });

    Route::controller(\App\Http\Controllers\ProducedBatchController::class)->group(function () {
        Route::get('/order-item-batch', 'index')->middleware('permission:order.create,restcaja');
        Route::get('/order-item-batch/{producedBatch}', 'show')->middleware('permission:order.create,restcaja');
        Route::put('/order-item-batch/{producedBatch}', 'update')->middleware('permission:order.edit,restcaja');
    });

    Route::controller(\App\Http\Controllers\CustomerController::class)->group(function () {
        Route::get('/customer', 'index')->middleware('permission:caja.list,kioskcaja');
        Route::get('/customer/{customer}', 'show')->middleware('permission:caja.list,kioskcaja');
        Route::post('/customer', 'store')->middleware('permission:caja.create,kioskcaja');
        Route::put('/customer/{customer}', 'update')->middleware('permission:caja.edit,kioskcaja');
        Route::delete('/customer/{customer}', 'destroy')->middleware('permission:caja.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\PaymentTypeController::class)->group(function () {
        Route::get('/payment-type', 'index')->middleware('permission:payment_type.list,kioskcaja');
        Route::get('/payment-type/{paymentType}', 'show')->middleware('permission:payment_type.list,kioskcaja');
        Route::post('/payment-type', 'store')->middleware('permission:payment_type.create,kioskcaja');
        Route::put('/payment-type/{paymentType}', 'update')->middleware('permission:payment_type.edit,kioskcaja');
        Route::delete('/payment-type/{paymentType}', 'destroy')->middleware('permission:payment_type.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\KioskCategoryController::class)->group(function () {
        Route::get('/kiosk/category', 'index')->name('index')->middleware('permission:kiosk_categories.list,kioskinvetario');
        Route::post('/kiosk/category', 'store')->name('store')->middleware('permission:kiosk_categories.create,kioskinvetario');
        Route::get('/kiosk/category/{kioskCategory}', 'show')->name('show')->middleware('permission:kiosk_categories.list,kioskinvetario');
        Route::put('/kiosk/category/{kioskCategory}', 'update')->name('update')->middleware('permission:kiosk_categories.edit,kioskinvetario');
        Route::delete('/kiosk/category/{kioskCategory}', 'destroy')->name('destroy')->middleware('permission:kiosk_categories.edit,kioskinvetario');
    });

    Route::controller(\App\Http\Controllers\KioskProductController::class)->group(function () {
        Route::get('/kiosk/product', 'index')->name('index')->middleware('permission:kiosk_products.list,kioskinvetario');
        Route::post('/kiosk/product', 'store')->name('store')->middleware('permission:kiosk_products.create,kioskinvetario');
        Route::get('/kiosk/product/{kioskProduct}', 'show')->name('show')->middleware('permission:kiosk_products.list,kioskinvetario');
        Route::post('/kiosk/product/{kioskProduct}', 'update')->name('update')->middleware('permission:kiosk_products.edit,kioskinvetario');
        Route::delete('/kiosk/product/{kioskProduct}', 'destroy')->name('destroy')->middleware('permission:kiosk_products.edit,kioskinvetario');
    });

    Route::controller(\App\Http\Controllers\KioskUnitController::class)->group(function () {
        Route::get('/kiosk/products/unit', 'index')->name('index')->middleware('permission:kiosk_products.list,kioskinvetario');
        Route::post('/kiosk/products/unit', 'store')->name('store')->middleware('permission:kiosk_products.create,kioskinvetario');
        Route::get('/kiosk/products/unit/{kioskUnit}', 'show')->name('show')->middleware('permission:kiosk_products.list,kioskinvetario');
        Route::put('/kiosk/products/unit/{kioskUnit}', 'update')->name('update')->middleware('permission:kiosk_products.edit,kioskinvetario');
        Route::delete('/kiosk/products/unit/{kioskUnit}', 'destroy')->name('destroy')->middleware('permission:kiosk_products.edit,kioskinvetario');
        Route::post('/kiosk/products/unit/bulk-update', 'bulkUpdate')->middleware('permission:kiosk_products.edit,kioskinvetario');
        Route::post('/kiosk/products/unit/bulk-delete', 'bulkDelete')->middleware('permission:kiosk_products.edit,kioskinvetario');
    });

    Route::controller(\App\Http\Controllers\KioskInvoiceController::class)->group(function () {
        Route::get('/kiosk/caja', 'index')->name('index')->middleware('permission:caja.list,kioskcaja');
        Route::post('/kiosk/caja', 'store')->name('store')->middleware('permission:caja.create,kioskcaja');
        Route::get('/kiosk/caja/{kioskUnit}', 'show')->name('show')->middleware('permission:caja.list,kioskcaja');
        Route::post('/kiosk/caja/{kioskUnit}', 'update')->name('update')->middleware('permission:caja.edit,kioskcaja');
        Route::delete('/kiosk/caja/{kioskUnit}', 'destroy')->name('destroy')->middleware('permission:caja.edit,kioskcaja');
    });
    Route::controller(\App\Http\Controllers\KioskInvoiceDetailController::class)->group(function () {
        Route::get('/kiosk/caja', 'index')->name('index')->middleware('permission:caja.list,kioskcaja');
        Route::post('/kiosk/caja', 'store')->name('store')->middleware('permission:caja.create,kioskcaja');
        Route::get('/kiosk/caja/{kioskUnit}', 'show')->name('show')->middleware('permission:caja.list,kioskcaja');
        Route::post('/kiosk/caja/{kioskUnit}', 'update')->name('update')->middleware('permission:caja.edit,kioskcaja');
        Route::delete('/kiosk/caja/{kioskUnit}', 'destroy')->name('destroy')->middleware('permission:caja.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\TaxController::class)->group(function () {
        Route::get('/kiosk/tax', 'index')->name('index')->middleware('permission:tax.list,kioskcaja');
        Route::post('/kiosk/tax', 'store')->name('store')->middleware('permission:tax.create,kioskcaja');
        Route::get('/kiosk/tax/{tax}', 'show')->name('show')->middleware('permission:tax.list,kioskcaja');
        Route::put('/kiosk/tax/{tax}', 'update')->name('update')->middleware('permission:tax.edit,kioskcaja');
        Route::delete('/kiosk/tax/{tax}', 'destroy')->name('destroy')->middleware('permission:tax.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\CustomerController::class)->group(function () {
        Route::get('/customer', 'index')->name('index')->middleware('permission:clientes.list,clientes');
        Route::post('/customer', 'store')->name('store')->middleware('permission:clientes.create,clientes');
        Route::get('/customer/{customer}', 'show')->name('show')->middleware('permission:clientes.list,clientes');
        Route::put('/customer/{customer}', 'update')->name('update')->middleware('permission:clientes.edit,clientes');
        Route::delete('/customer/{customer}', 'destroy')->name('destroy')->middleware('permission:clientes.edit,clientes');
    });
    Route::controller(\App\Http\Controllers\PaymentTypeController::class)->group(function () {
        Route::get('/payment-methods', 'index')->name('index')->middleware('permission:payment_type.list,kioskcaja');
        Route::post('/payment-methods', 'store')->name('store')->middleware('permission:payment_type.create,kioskcaja');
        Route::get('/payment-methods/{paymentType}', 'show')->name('show')->middleware('permission:payment_type.list,kioskcaja');
        Route::put('/payment-methods/{paymentType}', 'update')->name('update')->middleware('permission:payment_type.edit,kioskcaja');
        Route::delete('/payment-methods/{paymentType}', 'destroy')->name('destroy')->middleware('permission:payment_type.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\KioskInvoiceController::class)->group(function () {
        Route::get('/kiosk/invoice', 'index')->name('index')->middleware('permission:compras.list,kioskcaja');
        Route::post('/kiosk/invoice/generate-otp', 'generateOtp')->name('generateOtp')->middleware('permission:compras.create,kioskcaja');
        Route::post('/kiosk/invoice/{kioskInvoice}/verify-otp', 'verifyOtpAndComplete')->name('verifyOtpAndComplete')->middleware('permission:compras.create,kioskcaja');
        Route::post('/kiosk/invoice', 'store')->name('store')->middleware('permission:compras.create,kioskcaja');
        Route::get('/kiosk/invoice/{kioskInvoice}', 'show')->name('show')->middleware('permission:compras.list,kioskcaja');
        Route::put('/kiosk/invoice/{kioskInvoice}', 'update')->name('update')->middleware('permission:compras.edit,kioskcaja');
        Route::delete('/kiosk/invoice/{kioskInvoice}', 'destroy')->name('destroy')->middleware('permission:compras.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\KioskInvoiceDetailController::class)->group(function () {
        Route::get('/kiosk/invoice-detail', 'index')->name('index')->middleware('permission:compras.list,kioskcaja');
        Route::post('/kiosk/invoice-detail', 'store')->name('store')->middleware('permission:compras.create,kioskcaja');
        Route::get('/kiosk/invoice-detail/{kioskInvoiceDetail}', 'show')->name('show')->middleware('permission:compras.list,kioskcaja');
        Route::put('/kiosk/invoice-detail/{kioskInvoiceDetail}', 'update')->name('update')->middleware('permission:compras.edit,kioskcaja');
        Route::delete('/kiosk/invoice-detail/{kioskInvoiceDetail}', 'destroy')->name('destroy')->middleware('permission:compras.edit,kioskcaja');
    });

    Route::controller(\App\Http\Controllers\CashRegisterClosureController::class)->group(function () {
        Route::get('/cash-register/closure', 'index')->middleware('permission:caja.list,kioskcaja');
        Route::get('/cash-register/closure/current', 'getCurrentClosure')->middleware('permission:caja.list,kioskcaja');
        Route::get('/cash-register/closure/{cashRegisterClosure}', 'show')->middleware('permission:caja.list,kioskcaja');
        Route::post('/cash-register/closure', 'store')->middleware('permission:caja.create,kioskcaja');
        Route::put('/cash-register/closure/{cashRegisterClosure}', 'update')->middleware('permission:caja.edit,kioskcaja');
        Route::post('/cash-register/closure/{cashRegisterClosure}/close', 'close')->middleware('permission:caja.close,kioskcaja');
        Route::get('/cash-register/daily-report/{date}', 'getDailyReport')->middleware('permission:caja.report,kioskcaja');
        Route::delete('/cash-register/closure/{cashRegisterClosure}', 'destroy')->middleware('permission:caja.delete,kioskcaja');
    });

    // Rutas para reservas desde el módulo de kiosko
    Route::controller(\App\Http\Controllers\ReservationController::class)->group(function () {
        Route::get('/kiosk/check-active-reservation', 'checkActiveReservation')->middleware('permission:caja.list,kioskcaja');
        Route::get('/kiosk/active-reservations', 'getActiveReservationsForKiosk')->middleware('permission:caja.list,kioskcaja');
    });

    // =========================
    // Módulo de Reservas
    // Guard: reservas
    // =========================
    
    // Rutas de clientes para el módulo de reservas (deben ir ANTES de las rutas de reservas)
    Route::controller(\App\Http\Controllers\CustomerController::class)->group(function () {
        Route::get('/reservations/customers', 'index')->middleware('permission:customer.list,reservas');
        Route::get('/reservations/customers/{customer}', 'show')->middleware('permission:customer.view,reservas');
        Route::post('/reservations/customers', 'store')->middleware('permission:customer.create,reservas');
        Route::put('/reservations/customers/{customer}', 'update')->middleware('permission:customer.edit,reservas');
        Route::delete('/reservations/customers/{customer}', 'destroy')->middleware('permission:customer.delete,reservas');
    });

    // Rutas de métodos de pago accesibles desde el módulo de reservas (debe ir ANTES de /reservations/{reservation})
    Route::controller(\App\Http\Controllers\PaymentTypeController::class)->group(function () {
        Route::get('/reservations/payment-methods', 'indexForReservations')->middleware('permission:reservation.edit,reservas');
    });

    Route::controller(\App\Http\Controllers\ReservationController::class)->group(function () {
        Route::get('/reservations', 'index')->middleware('permission:reservation.list,reservas');
        Route::get('/reservations/availability', 'checkAvailability')->middleware('permission:reservation.list,reservas');
        Route::get('/reservations/daily-dashboard', 'dailyDashboard')->middleware('permission:reservation.list,reservas');
        Route::get('/reservations/marketing/report', 'marketingReport')->middleware('permission:reservation.report,reservas');
        Route::get('/reservations/occupancy/report', 'occupancyReport')->middleware('permission:reservation.report,reservas');
        Route::get('/reservations/revenue/report', 'revenueReport')->middleware('permission:reservation.report,reservas');
        Route::get('/reservations/cancellations/report', 'cancellationsReport')->middleware('permission:reservation.report,reservas');
        Route::post('/reservations', 'store')->middleware('permission:reservation.create,reservas');
        Route::get('/reservations/{reservation}', 'show')->middleware('permission:reservation.view,reservas');
        Route::put('/reservations/{reservation}', 'update')->middleware('permission:reservation.edit,reservas');
        Route::delete('/reservations/{reservation}', 'destroy')->middleware('permission:reservation.delete,reservas');
        Route::post('/reservations/{reservation}/certificate', 'generateCertificate')->middleware('permission:reservation.view,reservas');
        Route::get('/reservations/{reservation}/certificate/download', 'downloadCertificate')->middleware('permission:reservation.view,reservas');
        Route::post('/reservations/{reservation}/resend-email', 'resendEmail')->middleware('permission:reservation.edit,reservas');
        Route::post('/reservations/{reservation}/payments', 'addPayment')->middleware('permission:reservation.edit,reservas');
        Route::get('/reservations/{reservation}/audits', 'getAuditHistory')->middleware('permission:reservation.view,reservas');
        Route::post('/reservations/{reservation}/recalculate-price', 'recalculatePrice')->middleware('permission:reservation.edit,reservas');
        Route::post('/reservations/{reservation}/additional-services', 'addAdditionalService')->middleware('permission:reservation.edit,reservas');
        Route::delete('/reservations/{reservation}/additional-services/{reservationAdditionalService}', 'removeAdditionalService')->middleware('permission:reservation.edit,reservas');
        Route::get('/reservations/{reservation}/meal-consumption', 'getMealConsumption')->middleware('permission:reservation.view,reservas');
        Route::post('/reservations/{reservation}/check-in', 'checkIn')->middleware('permission:reservation.edit,reservas');
        Route::post('/reservations/{reservation}/check-out', 'checkOut')->middleware('permission:reservation.edit,reservas');
        Route::get('/reservations/{reservation}/checkout-certificate/download', 'downloadCheckoutCertificate')->middleware('permission:reservation.view,reservas');
        Route::post('/reservations/{reservation}/resend-checkout-email', 'resendCheckoutEmail')->middleware('permission:reservation.edit,reservas');
    });

    Route::controller(\App\Http\Controllers\ReservationSettingController::class)->group(function () {
        Route::get('/reservation-settings', 'index')->middleware('permission:reservation.edit,reservas');
        Route::put('/reservation-settings', 'update')->middleware('permission:reservation.edit,reservas');
    });

    Route::controller(\App\Http\Controllers\CancellationPolicyController::class)->group(function () {
        Route::get('/cancellation-policies', 'index')->middleware('permission:reservation.edit,reservas');
        Route::get('/cancellation-policies/applicable', 'getApplicablePolicy')->middleware('permission:reservation.list,reservas');
        Route::post('/cancellation-policies', 'store')->middleware('permission:reservation.edit,reservas');
        Route::get('/cancellation-policies/{cancellationPolicy}', 'show')->middleware('permission:reservation.view,reservas');
        Route::put('/cancellation-policies/{cancellationPolicy}', 'update')->middleware('permission:reservation.edit,reservas');
        Route::delete('/cancellation-policies/{cancellationPolicy}', 'destroy')->middleware('permission:reservation.edit,reservas');
    });

    Route::controller(\App\Http\Controllers\GoogleCalendarConfigController::class)->group(function () {
        Route::get('/google-calendar/config', 'index')->middleware('permission:reservation.edit,reservas');
        Route::get('/google-calendar/redirect-uri', 'getRedirectUri')->middleware('permission:reservation.edit,reservas');
        Route::get('/google-calendar/events', 'getEvents')->middleware('permission:reservation.list,reservas');
        Route::post('/google-calendar/config', 'store')->middleware('permission:reservation.edit,reservas');
        Route::get('/google-calendar/auth-url', 'getAuthUrl')->middleware('permission:reservation.edit,reservas');
        Route::post('/google-calendar/test-connection', 'testConnection')->middleware('permission:reservation.edit,reservas');
        Route::put('/google-calendar/toggle-active', 'toggleActive')->middleware('permission:reservation.edit,reservas');
        Route::delete('/google-calendar/config/{id}', 'destroy')->middleware('permission:reservation.edit,reservas');
    });

    Route::controller(\App\Http\Controllers\ReservationGuestController::class)->group(function () {
        Route::get('/reservations/{reservation}/guests', 'index')->middleware('permission:reservation.list,reservas');
        Route::post('/reservations/{reservation}/guests', 'store')->middleware('permission:reservation.edit,reservas');
        Route::put('/reservations/{reservation}/guests/{guest}', 'update')->middleware('permission:reservation.edit,reservas');
        Route::delete('/reservations/{reservation}/guests/{guest}', 'destroy')->middleware('permission:reservation.edit,reservas');
        Route::post('/reservations/{reservation}/guests/remove-duplicates', 'removeDuplicates')->middleware('permission:reservation.edit,reservas');
    });

    Route::controller(\App\Http\Controllers\RoomController::class)->group(function () {
        // Ruta básica para obtener habitaciones (sin permisos específicos, solo autenticación)
        Route::get('/rooms', 'listBasic'); // Para selectores en formularios (sin permisos específicos)
        Route::get('/rooms/{room}', 'show')->middleware('permission:room.view,reservas');
        Route::post('/rooms', 'store')->middleware('permission:room.create,reservas');
        Route::put('/rooms/{room}', 'update')->middleware('permission:room.edit,reservas');
        Route::delete('/rooms/{room}', 'destroy')->middleware('permission:room.delete,reservas');
    });

    Route::controller(\App\Http\Controllers\RoomTypeController::class)->group(function () {
        Route::get('/room-types', 'index')->middleware('permission:room_type.list,reservas');
        Route::get('/room-types/{roomType}', 'show')->middleware('permission:room_type.view,reservas');
        Route::post('/room-types', 'store')->middleware('permission:room_type.create,reservas');
        Route::put('/room-types/{roomType}', 'update')->middleware('permission:room_type.edit,reservas');
        Route::delete('/room-types/{roomType}', 'destroy')->middleware('permission:room_type.delete,reservas');
    });

    // Rutas de aforo de pasadía
    Route::controller(\App\Http\Controllers\DayPassCapacityController::class)->group(function () {
        Route::get('/day-pass-capacities', 'index')->middleware('permission:reservation.list,reservas');
        Route::get('/day-pass-capacities/availability', 'checkAvailability')->middleware('permission:reservation.list,reservas');
        Route::get('/day-pass-capacities/{dayPassCapacity}', 'show')->middleware('permission:reservation.list,reservas');
        Route::post('/day-pass-capacities', 'store')->middleware('permission:reservation.edit,reservas');
        Route::put('/day-pass-capacities/{dayPassCapacity}', 'update')->middleware('permission:reservation.edit,reservas');
        Route::delete('/day-pass-capacities/{dayPassCapacity}', 'destroy')->middleware('permission:reservation.edit,reservas');
    });

    // Servicios adicionales para reservas
    Route::controller(\App\Http\Controllers\AdditionalServiceController::class)->group(function () {
        Route::get('/additional-services', 'index')->middleware('permission:reservation.list,reservas');
        Route::get('/additional-services/{additionalService}', 'show')->middleware('permission:reservation.list,reservas');
        Route::post('/additional-services', 'store')->middleware('permission:reservation.edit,reservas');
        Route::put('/additional-services/{additionalService}', 'update')->middleware('permission:reservation.edit,reservas');
        Route::delete('/additional-services/{additionalService}', 'destroy')->middleware('permission:reservation.edit,reservas');
    });

    // Paquetes de servicios
    Route::controller(\App\Http\Controllers\ServicePackageController::class)->group(function () {
        Route::get('/service-packages', 'index')->middleware('permission:reservation.list,reservas');
        Route::get('/service-packages/{servicePackage}', 'show')->middleware('permission:reservation.list,reservas');
        Route::post('/service-packages', 'store')->middleware('permission:reservation.edit,reservas');
        Route::put('/service-packages/{servicePackage}', 'update')->middleware('permission:reservation.edit,reservas');
        Route::delete('/service-packages/{servicePackage}', 'destroy')->middleware('permission:reservation.edit,reservas');
    });

    // =========================
    // Módulo de Inventario de Habitaciones y Zonas Comunes
    // Guard: reservas
    // =========================

    // Categorías de inventario
    Route::controller(\App\Http\Controllers\RoomInventoryCategoryController::class)->group(function () {
        Route::get('/room-inventory/categories', 'index')->middleware('permission:room_inventory.category.list,reservas');
        Route::get('/room-inventory/categories/{roomInventoryCategory}', 'show')->middleware('permission:room_inventory.category.list,reservas');
        Route::post('/room-inventory/categories', 'store')->middleware('permission:room_inventory.category.create,reservas');
        Route::put('/room-inventory/categories/{roomInventoryCategory}', 'update')->middleware('permission:room_inventory.category.edit,reservas');
        Route::delete('/room-inventory/categories/{roomInventoryCategory}', 'destroy')->middleware('permission:room_inventory.category.delete,reservas');
    });

    // Artículos de inventario
    Route::controller(\App\Http\Controllers\RoomInventoryItemController::class)->group(function () {
        Route::get('/room-inventory/items', 'index')->middleware('permission:room_inventory.item.list,reservas');
        Route::get('/room-inventory/items/{roomInventoryItem}', 'show')->middleware('permission:room_inventory.item.list,reservas');
        Route::post('/room-inventory/items', 'store')->middleware('permission:room_inventory.item.create,reservas');
        Route::put('/room-inventory/items/{roomInventoryItem}', 'update')->middleware('permission:room_inventory.item.edit,reservas');
        Route::delete('/room-inventory/items/{roomInventoryItem}', 'destroy')->middleware('permission:room_inventory.item.delete,reservas');
    });

    // Zonas comunes
    Route::controller(\App\Http\Controllers\CommonAreaController::class)->group(function () {
        // Ruta básica para obtener zonas comunes (sin permisos específicos, solo autenticación)
        Route::get('/room-inventory/common-areas', 'listBasic'); // Para selectores en formularios (sin permisos específicos)
        Route::get('/room-inventory/common-areas/{commonArea}', 'show')->middleware('permission:room_inventory.common_area.list,reservas');
        Route::post('/room-inventory/common-areas', 'store')->middleware('permission:room_inventory.common_area.create,reservas');
        Route::put('/room-inventory/common-areas/{commonArea}', 'update')->middleware('permission:room_inventory.common_area.edit,reservas');
        Route::delete('/room-inventory/common-areas/{commonArea}', 'destroy')->middleware('permission:room_inventory.common_area.delete,reservas');
    });

    // Asignaciones de inventario
    Route::controller(\App\Http\Controllers\RoomInventoryAssignmentController::class)->group(function () {
        Route::get('/room-inventory/assignments', 'index')->middleware('permission:room_inventory.assignment.list,reservas');
        Route::get('/room-inventory/assignments/{roomInventoryAssignment}', 'show')->middleware('permission:room_inventory.assignment.list,reservas');
        Route::post('/room-inventory/assignments', 'store')->middleware('permission:room_inventory.assignment.create,reservas');
        Route::put('/room-inventory/assignments/{roomInventoryAssignment}', 'update')->middleware('permission:room_inventory.assignment.edit,reservas');
        Route::put('/room-inventory/assignments/{roomInventoryAssignment}/status', 'updateStatus')->middleware('permission:room_inventory.assignment.edit,reservas');
        Route::post('/room-inventory/assignments/{roomInventoryAssignment}/move', 'move')->middleware('permission:room_inventory.assignment.edit,reservas');
        Route::post('/room-inventory/assignments/{roomInventoryAssignment}/check', 'check')->middleware('permission:room_inventory.assignment.edit,reservas');
        Route::post('/room-inventory/assignments/{roomInventoryAssignment}/repair', 'registerRepair')->middleware('permission:room_inventory.assignment.edit,reservas');
        Route::delete('/room-inventory/assignments/{roomInventoryAssignment}', 'destroy')->middleware('permission:room_inventory.assignment.delete,reservas');
    });

    // Historial de inventario
    Route::controller(\App\Http\Controllers\RoomInventoryHistoryController::class)->group(function () {
        Route::get('/room-inventory/history', 'index')->middleware('permission:room_inventory.history.view,reservas');
        Route::get('/room-inventory/history/{roomInventoryHistory}', 'show')->middleware('permission:room_inventory.history.view,reservas');
        Route::get('/room-inventory/history/room/{roomId}', 'getByRoom')->middleware('permission:room_inventory.history.view,reservas');
        Route::get('/room-inventory/history/common-area/{commonAreaId}', 'getByCommonArea')->middleware('permission:room_inventory.history.view,reservas');
        Route::get('/room-inventory/history/item/{itemId}', 'getByItem')->middleware('permission:room_inventory.history.view,reservas');
        Route::get('/room-inventory/history/assignment/{assignmentId}', 'getByAssignment')->middleware('permission:room_inventory.history.view,reservas');
    });

    // ============================================
    // Módulo de Minibar
    // Guard: reservas
    // ============================================

    // Categorías de productos del minibar
    Route::controller(\App\Http\Controllers\MinibarProductCategoryController::class)->group(function () {
        Route::get('/minibar/categories', 'index')->middleware('permission:minibar.category.list,reservas');
        Route::get('/minibar/categories/{category}', 'show')->middleware('permission:minibar.category.list,reservas');
        Route::post('/minibar/categories', 'store')->middleware('permission:minibar.category.create,reservas');
        Route::put('/minibar/categories/{category}', 'update')->middleware('permission:minibar.category.edit,reservas');
        Route::delete('/minibar/categories/{category}', 'destroy')->middleware('permission:minibar.category.delete,reservas');
    });

    // Productos del minibar
    Route::controller(\App\Http\Controllers\MinibarProductController::class)->group(function () {
        Route::get('/minibar/products', 'index')->middleware('permission:minibar.product.list,reservas');
        Route::get('/minibar/products/sellable', 'getSellable')->middleware('permission:minibar.product.list,reservas');
        Route::get('/minibar/products/non-sellable', 'getNonSellable')->middleware('permission:minibar.product.list,reservas');
        Route::get('/minibar/products/{product}', 'show')->middleware('permission:minibar.product.list,reservas');
        Route::post('/minibar/products', 'store')->middleware('permission:minibar.product.create,reservas');
        Route::put('/minibar/products/{product}', 'update')->middleware('permission:minibar.product.edit,reservas');
        Route::delete('/minibar/products/{product}', 'destroy')->middleware('permission:minibar.product.delete,reservas');
    });

    // Stock de minibar por habitación
    Route::controller(\App\Http\Controllers\RoomMinibarStockController::class)->group(function () {
        Route::get('/rooms/{room}/minibar/stock', 'index')->middleware('permission:minibar.inventory.view,reservas');
        Route::post('/rooms/{room}/minibar/stock', 'store')->middleware('permission:minibar.inventory.record,reservas');
        Route::put('/rooms/{room}/minibar/stock/{stock}', 'update')->middleware('permission:minibar.inventory.record,reservas');
        Route::post('/rooms/{room}/minibar/restock', 'restock')->middleware('permission:minibar.inventory.record,reservas');
        Route::get('/rooms/{room}/minibar/stock/needing-restock', 'needingRestock')->middleware('permission:minibar.inventory.view,reservas');
    });

    // Inventario de minibar por reserva
    Route::controller(\App\Http\Controllers\RoomMinibarInventoryController::class)->group(function () {
        Route::get('/reservations/{reservation}/minibar/inventory', 'getByReservation')->middleware('permission:minibar.inventory.view,reservas');
        Route::post('/reservations/{reservation}/minibar/check-in', 'recordCheckIn')->middleware('permission:minibar.inventory.record,reservas');
        Route::post('/reservations/{reservation}/minibar/cleaning', 'recordCleaning')->middleware('permission:minibar.inventory.record,reservas');
        Route::post('/reservations/{reservation}/minibar/check-out', 'recordCheckOut')->middleware('permission:minibar.inventory.record,reservas');
    });

    // Cargos del minibar
    Route::controller(\App\Http\Controllers\ReservationMinibarChargeController::class)->group(function () {
        Route::get('/reservations/{reservation}/minibar/charges', 'getByReservation')->middleware('permission:minibar.charge.view,reservas');
        Route::delete('/reservations/minibar/charges/{charge}', 'destroy')->middleware('permission:minibar.charge.delete,reservas');
    });

    // Historial de reposiciones
    Route::controller(\App\Http\Controllers\MinibarRestockingLogController::class)->group(function () {
        Route::get('/minibar/restocking', 'index')->middleware('permission:minibar.inventory.view,reservas');
        Route::get('/minibar/restocking/room/{room}', 'getByRoom')->middleware('permission:minibar.inventory.view,reservas');
        Route::get('/minibar/restocking/product/{product}', 'getByProduct')->middleware('permission:minibar.inventory.view,reservas');
    });

    // ============================================
    // Módulo de Aseo y Mantenimiento
    // ============================================

    // Proveedores
    Route::controller(\App\Http\Controllers\SupplierController::class)->group(function () {
        Route::get('/suppliers', 'index')->middleware('permission:maintenance.supplier.manage,reservas');
        Route::get('/suppliers/{supplier}', 'show')->middleware('permission:maintenance.supplier.manage,reservas');
        Route::post('/suppliers', 'store')->middleware('permission:maintenance.supplier.manage,reservas');
        Route::put('/suppliers/{supplier}', 'update')->middleware('permission:maintenance.supplier.manage,reservas');
        Route::delete('/suppliers/{supplier}', 'destroy')->middleware('permission:maintenance.supplier.manage,reservas');
    });

    // Registros de Aseo
    Route::controller(\App\Http\Controllers\CleaningRecordController::class)->group(function () {
        // Rutas específicas primero (antes de las rutas con parámetros)
        Route::get('/cleaning/records', 'index')->middleware('permission:cleaning.list,reservas');
        Route::post('/cleaning/records', 'store')->middleware('permission:cleaning.create,reservas');
        Route::get('/cleaning/records/pending', 'getPending')->middleware('permission:cleaning.list,reservas');
        Route::get('/cleaning/records/statistics', 'getStatistics')->middleware('permission:cleaning.report,reservas');
        Route::get('/cleaning/records/room/{roomId}', 'getByRoom')->middleware('permission:cleaning.list,reservas');
        Route::get('/cleaning/records/common-area/{areaId}', 'getByCommonArea')->middleware('permission:cleaning.list,reservas');
        Route::get('/cleaning/records/reservation/{reservationId}', 'getByReservation')->middleware('permission:cleaning.list,reservas');
        Route::get('/cleaning/records/employee/{userId}', 'getByEmployee')->middleware('permission:cleaning.list,reservas');
        Route::post('/cleaning/records/{cleaningRecord}/complete', 'completePending')->middleware('permission:cleaning.create,reservas');
        // Rutas con parámetros dinámicos al final
        Route::get('/cleaning/records/{cleaningRecord}', 'show')->middleware('permission:cleaning.list,reservas');
        Route::put('/cleaning/records/{cleaningRecord}', 'update')->middleware('permission:cleaning.edit,reservas');
    });

    // Programación de Aseo
    Route::controller(\App\Http\Controllers\CleaningScheduleController::class)->group(function () {
        Route::get('/cleaning/schedule', 'index')->middleware('permission:cleaning.schedule,reservas');
        Route::post('/cleaning/schedule', 'store')->middleware('permission:cleaning.schedule,reservas');
        Route::get('/cleaning/schedule/{cleaningSchedule}', 'show')->middleware('permission:cleaning.schedule,reservas');
        Route::put('/cleaning/schedule/{cleaningSchedule}', 'update')->middleware('permission:cleaning.schedule,reservas');
        Route::get('/cleaning/schedule/room/{roomId}', 'getByRoom')->middleware('permission:cleaning.schedule,reservas');
        Route::get('/cleaning/schedule/common-area/{areaId}', 'getByCommonArea')->middleware('permission:cleaning.schedule,reservas');
        Route::get('/cleaning/schedule/due', 'getDueCleanings')->middleware('permission:cleaning.list,reservas');
        Route::post('/cleaning/schedule/{cleaningSchedule}/mark-cleaned', 'markAsCleaned')->middleware('permission:cleaning.create,reservas');
    });

    // Solicitudes de Mantenimiento
    Route::controller(\App\Http\Controllers\MaintenanceRequestController::class)->group(function () {
        Route::get('/maintenance/requests', 'index')->middleware('permission:maintenance.list,reservas');
        Route::post('/maintenance/requests', 'store')->middleware('permission:maintenance.request.create,reservas');
        Route::get('/maintenance/requests/{maintenanceRequest}', 'show')->middleware('permission:maintenance.list,reservas');
        Route::put('/maintenance/requests/{maintenanceRequest}', 'update')->middleware('permission:maintenance.request.edit,reservas');
        Route::post('/maintenance/requests/{maintenanceRequest}/assign', 'assign')->middleware('permission:maintenance.request.assign,reservas');
        Route::post('/maintenance/requests/{maintenanceRequest}/complete', 'complete')->middleware('permission:maintenance.request.edit,reservas');
        Route::post('/maintenance/requests/{maintenanceRequest}/cancel', 'cancel')->middleware('permission:maintenance.request.edit,reservas');
        Route::get('/maintenance/requests/room/{roomId}', 'getByRoom')->middleware('permission:maintenance.list,reservas');
        Route::get('/maintenance/requests/common-area/{areaId}', 'getByCommonArea')->middleware('permission:maintenance.list,reservas');
        Route::get('/maintenance/requests/status/{status}', 'getByStatus')->middleware('permission:maintenance.list,reservas');
    });

    // Trabajos de Mantenimiento
    Route::controller(\App\Http\Controllers\MaintenanceWorkController::class)->group(function () {
        Route::get('/maintenance/works', 'index')->middleware('permission:maintenance.list,reservas');
        Route::post('/maintenance/works', 'store')->middleware('permission:maintenance.work.create,reservas');
        Route::get('/maintenance/works/{maintenanceWork}', 'show')->middleware('permission:maintenance.list,reservas');
        Route::put('/maintenance/works/{maintenanceWork}', 'update')->middleware('permission:maintenance.work.edit,reservas');
        Route::get('/maintenance/works/room/{roomId}', 'getByRoom')->middleware('permission:maintenance.list,reservas');
        Route::get('/maintenance/works/common-area/{areaId}', 'getByCommonArea')->middleware('permission:maintenance.list,reservas');
        Route::get('/maintenance/works/supplier/{supplierId}', 'getBySupplier')->middleware('permission:maintenance.list,reservas');
        Route::get('/maintenance/works/warranty-expiring', 'getWarrantyExpiring')->middleware('permission:maintenance.list,reservas');
        Route::get('/maintenance/works/costs-report', 'getCostsReport')->middleware('permission:maintenance.report,reservas');
    });

    // Consumo de alimentación de reservas
    Route::controller(\App\Http\Controllers\ReservationMealConsumptionController::class)->group(function () {
        Route::get('/reservations/{reservationId}/meal-consumption', 'index')->middleware('permission:reservation.view,reservas');
        Route::post('/reservations/meal-consumption', 'store')->middleware('permission:reservation.edit,reservas');
        Route::get('/reservations/meal-consumption/{reservationMealConsumption}', 'show')->middleware('permission:reservation.view,reservas');
        Route::delete('/reservations/meal-consumption/{reservationMealConsumption}', 'destroy')->middleware('permission:reservation.edit,reservas');
    });

    // Comidas de trabajadores
    Route::controller(\App\Http\Controllers\EmployeeMealController::class)->group(function () {
        Route::get('/employee-meals', 'index')->middleware('permission:order.list,restcaja');
        Route::get('/employee-meals/{employeeMeal}', 'show')->middleware('permission:order.list,restcaja');
        Route::post('/employee-meals', 'store')->middleware('permission:order.create,restcaja');
        Route::put('/employee-meals/{employeeMeal}', 'update')->middleware('permission:order.edit,restcaja');
        Route::delete('/employee-meals/{employeeMeal}', 'destroy')->middleware('permission:order.edit,restcaja');
        Route::get('/employee-meals/report', 'getReport')->middleware('permission:order.list,restcaja');
    });
});
