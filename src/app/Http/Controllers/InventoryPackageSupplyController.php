<?php

namespace App\Http\Controllers;

use App\Models\InventoryPackage;
use App\Models\InventoryPackageSupply;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class InventoryPackageSupplyController extends Controller
{
    public function list(Request $request){

        $packages = InventoryPackageSupply::with("user")->get();
        return response($packages, Response::HTTP_OK);
    }
    public function show(InventoryPackageSupply $package){
        return response($package, Response::HTTP_OK);
    }

    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'package_id' => 'required|exists:inventory_packages,id',
            'supply_date' => 'required|date',
            'stock' => 'required|min:1|numeric',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $request["user_id"]=Auth::id();
        $package = InventoryPackageSupply::create($request->all());
        $pkg_base = InventoryPackage::find($request["package_id"]);
        $pkg_base->stock += $package->stock;
        $pkg_base->save();
        return response(['msg' => "Package Supplied", 'package'=>$package], Response::HTTP_OK);
    }

    public function update(Request $request, InventoryPackageSupply $sp){

        $validation = Validator::make($request->all(), [
            'package_id' => 'sometimes|exists:inventory_packages,id',
            'supply_date' => 'sometimes|date',
            'stock' => 'sometimes|min:1|numeric',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $sp->update($request->all());
        return response(['msg' => "Package Supplied", 'package'=>$sp], Response::HTTP_OK);
    }
}
