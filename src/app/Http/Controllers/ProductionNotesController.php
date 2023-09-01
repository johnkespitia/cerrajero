<?php

namespace App\Http\Controllers;

use App\Models\ProductionNotes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductionNotesController extends Controller
{
    public function save(Request $request){

        $validation = Validator::make($request->all(), [
            'note' => 'required|max:255',
            'order_item_id' => 'required|exists:order_items,id|max:125',
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $request["user_id"]=Auth::id();
        $role = ProductionNotes::create($request->all());
        return response(['msg' => "Permission saved", 'rol'=>$role], Response::HTTP_OK);
    }
}
