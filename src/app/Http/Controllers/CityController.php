<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\City;
class CityController extends Controller
{
    public function index(){
        try{
            return City::all();
        }catch (\Exception $exception){
            return response()->json(["errors" => "Server Error {$exception->getMessage()}"  ] , 403);
        }
    }
}
