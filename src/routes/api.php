<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
Use App\Business;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//Rutas de gestion de usuarios:
Route::post('register' , 'CustomerController@store');
Route::post('login', 'Auth\LoginController@login');
Route::post('password/reset', 'Auth\ResetPasswordController@reset');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail');

//page routes
Route::get('site-banners', 'HomeController@banners');
Route::get('verify-coupon/{name}', 'CouponController@verify');
Route::get('site-version', 'CategoryController@getVersion');
Route::get('site-cities', 'CityController@index');
Route::get('site-category', 'CategoryController@activeCategories');
Route::get('site-provider', 'ProviderController@index');
Route::get('search-product/{term}', 'ProductController@search');
Route::post('shipping-cost', 'ShippingController@quotation');
Route::get('site-products/{idCategory}', 'ProductController@activeProducts');
Route::get('site-products', 'ProductController@activeAllProducts');
Route::get('site-products-provider/{providerId}', 'ProductController@activeProviderProducts');

Route::get('healthcheck', 'HomeController@healthcheck');
Route::post('contact-us', 'HomeController@sendMessage');

Route::group(['middleware' => 'auth:api'], function() {
    //Logout
    Route::post('logout', 'Auth\LoginController@logout');
    //User routes
    Route::get('user', 'UserController@index');
    Route::get('user/{user}', 'UserController@show');
    Route::post('user', 'UserController@store');
    Route::put('user/{user}', 'UserController@update');
    Route::delete('user/{user}', 'UserController@delete');
    
    //Providers routes
    Route::get('provider', 'ProviderController@index');
    Route::get('provider/{provider}', 'ProviderController@show');
    Route::post('provider', 'ProviderController@store');
    Route::post('provider/{provider}', 'ProviderController@update');
    Route::delete('provider/{provider}', 'ProviderController@destroy');
    
    //Categories routes
    Route::get('category', 'CategoryController@index');
    Route::post('category', 'CategoryController@store');
    Route::post('category/{category}', 'CategoryController@update');
    
    //Products routes
    Route::get('products/{idCategory}', 'ProductController@index');
    Route::post('products', 'ProductController@store');
    Route::post('products-images/{product}', 'ProductController@storeImages');
    Route::delete('products-images/{product}', 'ProductController@destroyImage');
    Route::post('products/{product}', 'ProductController@update');
    Route::post('category/{category}', 'CategoryController@update');
    Route::get('attributes', 'AttributeController@list');
    Route::get('attributes', 'AttributeController@list');
    Route::post('add-attribute/{product}', 'ProductController@addAttrib');
    Route::post('presentation/{product}', 'ProductPresentationController@store');
    Route::get('presentation-available/{presentation}', 'ProductPresentationController@presentationAvailable');
    Route::put('price-provider/{productPrice}/{productPresentation}', 'ProductPresentationController@updatePriceProvider');
    Route::post('price-provider/{productPresentation}', 'ProductPresentationController@storePriceProvider');
    
    //Rutas de cliente
    Route::get('customers' , 'CustomerController@index');
    Route::get('customers/{idcustomer}' , 'CustomerController@show');
    Route::put('customers/{customer}' , "CustomerController@update");
    
    
    //Rutas de direccion
    Route::post('addresses' , 'AddressController@store');
    Route::get('addresses/{idcustomer}' , 'AddressController@show');
    Route::put('addresses/{address}' , 'AddressController@update');

    //Routes Payment Method
    Route::get('payment-methods', 'PaymentMethodController@index');

    //Routes Orders
    Route::post('create-order', 'OrderController@store');
    Route::get('user-orders', 'OrderController@list');
    Route::get('get-order/{order}', 'OrderController@get');
    Route::get('admin-orders', 'OrderController@listAdmin');
    //CS
    Route::post('create-ticket', 'CustomerServiceController@store');
    Route::post('client-message', 'CustomerServiceController@newMessageClient');
    Route::post('admin-message', 'CustomerServiceController@newMessageAdmin');
    Route::get('get-ticket/{id}', 'CustomerServiceController@showByOrder');
    

    //Routes CMS
    Route::post('banner', 'CmsController@storeBanner');
    Route::delete('banner/{banner}', 'CmsController@destroyBanner');
    Route::get('banner', 'CmsController@index');

    Route::post('featured-products', 'CmsController@addFeaturedProduct');
    Route::delete('featured-products/{featuredProduct}', 'CmsController@destroyFeaturedProduct'); 

    //Cupones
    Route::get('coupon', 'CouponController@index');
    Route::get('coupon/{cupon}', 'CouponController@show');
    Route::post('coupon', 'CouponController@create');
    Route::post('coupon/{cupon}', 'CouponController@update');
});
