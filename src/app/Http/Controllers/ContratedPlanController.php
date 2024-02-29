<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\Professor;
use App\Models\ContratedPlan;
use Illuminate\Http\Request;

class ContratedPlanController extends Controller
{

    public function list(Request $request){

        $cps = ContratedPlan::with("professor.user")->with("students.user")->get();
        return response($cps, Response::HTTP_OK);
    }

    public function create(Request $request){

        $validation = Validator::make($request->all(), [
            'starting_date' => 'required|date',
            'expiration_date' => 'required|date|after:starting_date',
            'short_description' => 'required|max:200',
            'plan_extra_details' => 'required',
            'classes' => 'required|min:1|integer',
            'taked_classes' => 'required|min:0|lte:classes|integer',
            'professor_id' => 'integer|exists:professors,id',
            'hourly_fee' => 'numeric|min:0'
        ], [
            'required' => 'The :attribute is required',
            'unique' => 'The :attribute exists in the database',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cplan = ContratedPlan::create($validation->validated());
        return response(['msg' => "Plan saved", 'rol'=>$cplan], Response::HTTP_OK);
    }

    public function update(Request $request, ContratedPlan $cplan)
    {
        $validator = Validator::make($request->all(), [
            'starting_date' => 'date',
            'expiration_date' => 'date|after:starting_date',
            'short_description' => 'max:200',
            'classes' => 'min:1|integer',
            'taked_classes' => 'min:0|lte:classes|integer',
            'professor_id' => 'integer|exists:professors,id',
            'hourly_fee' => 'numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cplan->update($validator->validated());

        return response()->json(['message' => 'Plan updated successfully'], 200);
    }

    public function destroy(ContratedPlan $cplan)
    {
        $cplan->delete();
        return response()->json(['message' => 'Link deleted successfully'], 200);
    }

    public function addStudents(Request $request, ContratedPlan $cplan)
    {
        $validator = Validator::make($request->all(), [
            'students' => 'nullable|array',
            'students.*' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $cplan->students()->sync($request->input('students'));
        return response()->json(['message' => 'students added successfully'], 200);
    }

}
