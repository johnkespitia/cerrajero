<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('health-check', 'HomeController@healthcheck');

Auth::routes();

Route::get('payment/approved/{order}', 'OrderController@approvePayment');
Route::get('payment/failed/{order}', 'OrderController@failedPayment');
Route::get('payment/pending/{order}', 'OrderController@pendingPayment');