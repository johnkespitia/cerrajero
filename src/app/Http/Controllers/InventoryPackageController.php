<?php

namespace App\Http\Controllers;

use App\Models\InventoryPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InventoryPackageController extends Controller
{
    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'package_name' => 'required|max:20',
            'stock' => 'required|min:0|numeric',
            'units_by' => 'required|min:1|numeric',
            'status' => 'required|boolean',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $package = InventoryPackage::create([
            "package_name"=> $request->package_name,
            "stock"=> $request->stock,
            "units_by"=> $request->units_by,
            "status"=> $request->status,
        ]);
        return response(['msg' => "Package saved", 'package'=>$package], Response::HTTP_OK);
    }

    public function list(Request $request){

        $packages = InventoryPackage::with("supplies")->with("consumes.batch")->get();
        return response($packages, Response::HTTP_OK);
    }

    public function show(InventoryPackage $package){
        return response($package, Response::HTTP_OK);
    }

    public function update(Request $request,InventoryPackage $package){

        $validation = Validator::make($request->all(), [
            'package_name' => 'sometimes|max:20',
            'stock' => 'sometimes|min:0|numeric',
            'units_by' => 'sometimes|min:1|numeric',
            'status' => 'sometimes|boolean',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $package->update($validation->validated());
        return response(['msg' => "Guard saved", 'guard'=>$package], Response::HTTP_OK);
    }
}
