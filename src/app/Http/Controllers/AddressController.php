<?php

namespace App\Http\Controllers;

use App\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Address;


class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //validation rules
        $validation_rules = [
            "latitude" => 'required|numeric',
            "longitude" => 'required|numeric',
            "address" => 'required',
            "customer_id" => 'required|numeric',
            "city_id" => 'required|numeric',
            "phone" => 'required|numeric',
            "is_principal" => 'required|boolean',

        ];

        //validation
        $validador = Validator::make($request->all(), $validation_rules);
        if ($validador->fails()) {
            return response()->json(['errors' => $validador->errors()], 403);
        }


        try {
            //select count of
            // all Principal Addresses of input's Customer
            $principal_count = Address::where('is_principal', true)
                                        ->where('customer_id', $request->input('customer_id'))
                                        ->get()
                                        ->count();

            //create

            //verify Customer doesn't have Principal Addresses, or address to insert is zero(not principal)
            if ($principal_count === 0 or $request->input('is_principal') == 0) {
                //If Customer doesn't have Principal Addresses
                //Create Address
                $address = new Address();
                $address->latitude = $request->input('latitude');
                $address->longitude = $request->input('longitude');
                $address->address = $request->input('address');
                $address->phone = $request->input('phone');
                $address->arrival_directions = $request->input('arrival_directions');
                $address->address_remarks = $request->input('address_remarks');
                $address->customer_id = $request->input('customer_id');
                $address->city_id = $request->input('city_id');
                $address->address_type = $request->input('address_type');
                $address->is_principal = $request->input('is_principal');
                $address->state = 1;
                $address->save();


                //return 201 response
                return response()->json($address, 201);
            } else {
                //If Customer  has Principal Addresses
                //return 403 error
                return response()->json(['errors' => "Customer already has principal address "], 403);
            }

        } catch (\Exception $Exception) {
            //Database and many other exceptions
            return response()->json(["errors" => "Server Error"], 403);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($idcustomer)
    {
        try{
            $dire = Customer::find($idcustomer)->addresses()->get();

        }catch ( \Exception $Exception){
            return response()->json(["errors" => "Server Error"  ] , 403);
        }

        return response()->json($dire , 201);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,Address $address)
    {
        //validation rules
        $validation_rules = [
            "latitude" => 'numeric',
            "longitude" => 'numeric',
            "city_id" => 'numeric|exists:city,id',
            "phone" => 'numeric',
            "is_principal" => 'boolean',
            "state" => 'boolean'

        ];

        //validation
        $validador = Validator::make($request->all(), $validation_rules);
        if ($validador->fails()) {
            return response()->json(['errors' => $validador->errors()], 403);
        }
            //select count of
            // all Principal Addresses of input's Customer
            $principal_count = Address::where('is_principal', true)
                            ->where('customer_id', $request->input('customer_id'))
                            ->where("id", "!=",$address->id)
                             ->get()
                            ->count();

            //create

            //verify Customer doesn't have Principal Addresses, or address to insert is zero(not principal)
            if ($principal_count === 0 || $request->input('is_principal') == 0) {
                //If Customer doesn't have Principal Addresses
                //Create Address
                $address->update($request->all());
                return response()->json($address, 200);
            } else {
                //If Customer  has Principal Addresses
                //return 403 error
                return response()->json(['errors' => "Customer already has principal address "], 403);
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
