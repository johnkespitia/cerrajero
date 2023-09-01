<?php

namespace App\Http\Controllers;

use App\Models\InventoryPackage;
use App\Models\InventoryPackageConsume;
use App\Models\InventoryPackageSupply;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InventoryPackageConsumeController extends Controller
{
    public function list(Request $request){

        $packages = InventoryPackageConsume::with("user")->get();
        return response($packages, Response::HTTP_OK);
    }
    public function show(InventoryPackageConsume $package){
        return response($package, Response::HTTP_OK);
    }

    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'package_id' => 'required|exists:inventory_packages,id',
            'consumed_date' => 'required|date',
            'spoilt' => 'required|boolean',
            'stock_consumed' => 'required|min:1|numeric',
            'batch_id' => 'sometimes|exists:produced_batches,id',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $request["user_id"]=Auth::id();
        $package = InventoryPackageConsume::create($request->all());
        $pkg_base = InventoryPackage::find($request["package_id"]);
        $pkg_base->stock = $pkg_base->stock - $package->stock_consumed;
        $pkg_base->save();
        return response(['msg' => "Package Supplied", 'package'=>$package], Response::HTTP_OK);
    }

    public function update(Request $request, InventoryPackageConsume $sp){

        $validation = Validator::make($request->all(), [
            'package_id' => 'sometimes|exists:inventory_packages,id',
            'consumed_date' => 'sometimes|date',
            'spoilt' => 'sometimes|boolean',
            'stock_consumed' => 'sometimes|min:1|numeric',
            'batch_id' => 'sometimes|exists:produced_batches,id',
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
