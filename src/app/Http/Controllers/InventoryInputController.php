<?php

namespace App\Http\Controllers;

use App\Models\inventoryCategory;
use App\Models\inventoryInput;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InventoryInputController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $inputs = inventoryInput::with("category", "measure")->get();
        return response($inputs, Response::HTTP_OK);
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
            'name' => 'required|unique:inventory_inputs|max:250',
            'serial' => 'required|unique:inventory_inputs|max:50',
            'active' => 'required|boolean',
            'category_id' => 'required|exists:inventory_categories,id',
            'measure_id' => 'required|exists:inventory_measures,id',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inventoryInput = inventoryInput::create([
            "name"=> $request->name,
            "serial"=> $request->serial,
            "active"=> $request->active,
            "category_id"=> $request->category_id,
            "measure_id"=> $request->measure_id,
        ]);
        $inventoryInput->category;
        $inventoryInput->measure;
        return response(['msg' => "Input saved", 'inventory_input'=>$inventoryInput], Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\inventoryInput  $inventoryInput
     * @return \Illuminate\Http\Response
     */
    public function show(inventoryInput $inventoryInput)
    {
        $inventoryInput->category;
        $inventoryInput->measure;
        return response($inventoryInput, Response::HTTP_OK);


    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\inventoryInput  $inventoryInput
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, inventoryInput $inventoryInput)
    {

        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|unique:inventory_inputs,name,'.$inventoryInput->id.'|max:250',
            'serial' => 'sometimes|unique:inventory_inputs,serial,'.$inventoryInput->id.'|max:50',
            'active' => 'sometimes|boolean',
            'category_id' => 'sometimes|exists:inventory_categories,id',
            'measure_id' => 'sometimes|exists:inventory_measures,id',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryInput->update([
            "name"=> $request->name??$inventoryInput->name,
            "serial"=> $request->serial??$inventoryInput->serial,
            "active"=> $request->active??$inventoryInput->active,
            "category_id"=> $request->category_id??$inventoryInput->category->id,
            "measure_id"=> $request->measure_id??$inventoryInput->measure->id,
        ]);
        $inventoryInput->category;
        $inventoryInput->measure;
        return response(['msg' => "Input saved", 'inventory_input'=>$inventoryInput], Response::HTTP_OK);
    }

}
