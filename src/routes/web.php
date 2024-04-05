<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email-test', function () {
    $data = [
        'bg' => asset('storage/mail_assets/mail-bg1.png'),
        'main_title' => "¿Quieres tener una hora de clase GRATIS en tu plan?",
        'subtitle' => "Como lo bueno se recomienda, conoce nuestro plan de referidos.",
        'main_btn_url' => "https://docs.google.com/forms/d/e/1FAIpQLSfKq1U8RV47cqS5x31bE_0kudPCWP9uDeJfVq_B32FF2Id9Dw/viewform?usp=send_form",
        'main_btn_title' => "Recomiéndanos",
      ];

      Mail::send('email.welcome-professor', $data, function($message) {
        $message->to('jcespitia1@gmail.com')->subject('Asunto del correo');
      });
});


Route::get('api-docs', '\L5Swagger\Http\Controllers\SwaggerController@api');
