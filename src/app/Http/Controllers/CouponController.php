<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Cupon;
use Illuminate\Support\Facades\Validator;
class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            return Cupon::all();
        }catch (\Exception $exception){
            return response()->json(["errors" => "Server Error {$exception->getMessage()}"  ] , 403);
        }

    }

    /**
     * Store a newly created customer in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //validation rules
        $validation_rules = [
            "name" => 'required|unique:cupons',
            "value" => 'required|numeric',
            "type" => 'required|in:percentage,price' ,
            "expiration_date" => 'required' ,
            "uses" => 'required|numeric',
            "active" => 'required' 
        ];
        //validation
        $validador = Validator::make($request->all() , $validation_rules);
        if($validador->fails()){
            return response()->json(['errors'=>$validador->errors()], 400);
        }
        
        try{
            //Create User
            $cupon =  Cupon::create($request->all());
            return response()->json($cupon, 201);
        }catch(\Exception $exception){
            return response()->json(["errors" => ["Server Error"=>$exception->getMessage()]  ] , 400);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Cupon $cupon)
    {
        try{
            return $cupon;
        }catch(\Exception $exception){
            //Database and many other exceptions
            return response()->json(["errors" => "Server Error"  ] , 403);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function verify($name)
    {
        try{
            return Cupon::where("name",$name)
            ->where("uses",">","0")
            ->where("active","1")
            ->where("expiration_date",">=", date("Y-m-d"))
            ->first();
        }catch(\Exception $exception){
            //Database and many other exceptions
            return response()->json(["errors" => "Server Error ".$exception->getMessage()  ] , 403);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,Cupon $cupon)
    {
        //validation rules
        $validation_rules = [
            "name" => 'unique:cupons',
            "value" => 'numeric',
            "type" => 'in:percentage,price' ,
            "uses" => 'numeric'
        ];
        //validation
        $validador = Validator::make($request->all() , $validation_rules);
        if($validador->fails()){
            return response()->json(['errors'=>$validador->errors()], 400);
        }

        //update customer and user:
        try{
            $cupon->update($request->all());
            return response()->json(Cupon::find($cupon->id), 201);
        }catch(\Exception $exception){

            return response()->json(['errors'=>$exception->getMessage()], 403);
        }
    }



    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
