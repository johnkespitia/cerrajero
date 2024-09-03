<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\PaymentMethod;
class PaymentMethodController extends Controller
{
    public function index(){
        try{
            return PaymentMethod::where("status",1)->get();
        }catch (\Exception $exception){
            return response()->json(["errors" => "Server Error {$exception->getMessage()}"  ] , 403);
        }
    }
}
