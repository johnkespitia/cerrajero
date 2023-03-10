<?php

namespace App\Http\Controllers;

use App\Models\inventoryCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class InventoryCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categories = inventoryCategory::with("inputTypes")->get();
        return response($categories, Response::HTTP_OK);
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
            'name' => 'required|unique:inventory_categories|max:100',
            'input_type_id' => 'required|exists:inventory_type_inputs,id',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryCategory = inventoryCategory::create([
            "name"=> $request->name,
            "input_type_id"=> $request->input_type_id,
        ]);
        $inventoryCategory->inputTypes;
        return response(['msg' => "Category saved", 'inventory_category'=>$inventoryCategory], Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\inventoryCategory  $inventoryCategory
     * @return \Illuminate\Http\Response
     */
    public function show(inventoryCategory $inventoryCategory)
    {
        $inventoryCategory->inputTypes;
        return response($inventoryCategory, Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\inventoryCategory  $inventoryCategory
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, inventoryCategory $inventoryCategory)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|unique:inventory_categories|max:100',
            'input_type_id' => 'sometimes|exists:inventory_type_inputs,id',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $inventoryCategory->update([
            "name"=> $request->name,
            "input_type_id"=> $request->input_type_id??$inventoryCategory->input_type_id,
        ]);
        $inventoryCategory->inputTypes;
        return response(['msg' => "Category saved", 'inventory_category'=>$inventoryCategory], Response::HTTP_OK);
    }
}
