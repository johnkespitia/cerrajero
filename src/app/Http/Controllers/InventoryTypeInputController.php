<?php

namespace App\Http\Controllers;

use App\Models\InventoryTypeInput;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InventoryTypeInputController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $typeInput = InventoryTypeInput::all();
        return response($typeInput, Response::HTTP_OK);
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
            'name' => 'required|unique:inventory_type_inputs|max:100',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryTypeInput = InventoryTypeInput::create([
            "name"=> $request->name,
        ]);
        return response(['msg' => "Input Type saved", 'inputType'=>$inventoryTypeInput], Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\InventoryTypeInput  $inventoryTypeInput
     * @return \Illuminate\Http\Response
     */
    public function show(InventoryTypeInput $inventoryTypeInput)
    {
        return response($inventoryTypeInput, Response::HTTP_OK);
    }

    public function update(Request $request, InventoryTypeInput $inventoryTypeInput)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:inventory_type_inputs,name,'.$inventoryTypeInput->id.'|max:100',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryTypeInput->update([
            "name"=> $request->name
        ]);
        return response(['msg' => "Input Type saved", 'inputType'=> $inventoryTypeInput], Response::HTTP_OK);
    }
}
