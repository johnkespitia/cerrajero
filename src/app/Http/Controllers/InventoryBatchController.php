<?php

namespace App\Http\Controllers;

use App\Models\InventoryBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InventoryBatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $batchReady = InventoryBatch::with("input.measure")->where("quantity",">",0)->where("active",1)->whereDate("expiration_date",">=", now())->get();
        $batchConsumed = InventoryBatch::with("input.measure")->where("quantity","<=",0)->where("active",1)->whereDate("expiration_date",">=", now())->get();
        $batchInactive = InventoryBatch::with("input.measure")->where("active",0)->whereDate("expiration_date",">=", now())->get();
        $batchExpired = InventoryBatch::with("input.measure")->whereDate("expiration_date","<", now())->get();
        return response([
            "ready"=>$batchReady,
            "consumed"=>$batchConsumed,
            "inactive"=>$batchInactive,
            "expired"=>$batchExpired,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:inventory_batches|max:100',
            'active' => 'required|boolean',
            'serial' => 'required|unique:inventory_batches',
            'input_id' => 'required|exists:inventory_inputs,id',
            'expiration_date' => 'required|date',
            'brand' => 'required|max:250',
            'price' => 'required|integer|min:0',
            'quantity' => 'required|integer|min:0',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryBatch = InventoryBatch::create([
            'name' => $request->name,
            'active' => $request->active,
            'serial' => $request->serial,
            'input_id' => $request->input_id,
            'expiration_date' => $request->expiration_date,
            'brand' => $request->brand,
            'price' => $request->price,
            'quantity' => $request->quantity,
        ]);
        $inventoryBatch->input;
        return response(['msg' => "Batch saved", 'inventory_batch'=>$inventoryBatch], Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\inventoryBatch  $inventoryBatch
     * @return \Illuminate\Http\Response
     */
    public function show(InventoryBatch $inventoryBatch)
    {
        $inventoryBatch->input;
        return response($inventoryBatch, Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\inventoryBatch  $inventoryBatch
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, InventoryBatch $inventoryBatch)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'unique:inventory_batches,name,'.$inventoryBatch->id.'|max:100',
            'active' => 'boolean',
            'serial' => 'unique:inventory_batches,serial,'.$inventoryBatch->id,
            'input_id' => 'exists:inventory_inputs,id',
            'expiration_date' => 'date',
            'brand' => 'max:250',
            'price' => 'min:0',
            'quantity' => 'min:0',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response([$validation->errors()->toArray()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryBatch->update([
            'name' => $request->name??$inventoryBatch->name,
            'active' => $request->active??$inventoryBatch->active,
            'serial' => $request->serial??$inventoryBatch->serial,
            'input_id' => $request->input_id??$inventoryBatch->input_id,
            'expiration_date' => $request->expiration_date??$inventoryBatch->expiration_date,
            'brand' => $request->brand??$inventoryBatch->brand,
            'price' => $request->price??$inventoryBatch->price,
            'quantity' => $request->quantity??$inventoryBatch->quantity,
        ]);
        $inventoryBatch->input;
        return response(['msg' => "Batch saved", 'inventory_batch'=>$inventoryBatch], Response::HTTP_OK);
    }
}
