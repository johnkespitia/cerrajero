<?php

namespace App\Http\Controllers;

use App\Models\ImpartedClass;
use App\Models\ContratedPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImpartedClassController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrated_plan_id' => 'required|exists:contrated_plans,id',
            'scheduled_class' => 'required|date',
            'comments' => 'min:3',
            'professor_atendance' => 'boolean',
            'class_time'=> 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $ctdPlan = ContratedPlan::find($validator->validated()["contrated_plan_id"]);
        $impartedClasses = sizeof($ctdPlan->imparted_classes);
        if(!empty($ctdPlan) && $ctdPlan->classes <= $impartedClasses){
            return response()->json(['errors' => "Can't create a new class, all classes assigned was created {$impartedClasses}"], 422);
        }
        $ic = ImpartedClass::create($validator->validated());
        if($ic->professor_atendance){
            $ic->contrated_plan->taked_classes += 1;
            $ic->contrated_plan->save();
        }
        return response()->json(['message' => 'Imparted Class created successfully'], 201);
    }

    public function update(Request $request, ImpartedClass $ic)
    {
        $validator = Validator::make($request->all(), [
            'contrated_plan_id' => 'exists:contrated_plans,id',
            'scheduled_class' => 'date',
            'comments' => 'min:3',
            'professor_atendance' => 'boolean',
            'class_time'=> 'date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ic->update($validator->validated());
        if($ic->professor_atendance){
            $ic->contrated_plan->taked_classes += 1;
            $ic->contrated_plan->save();
        }
        return response()->json(['message' => 'Imparted Class updated successfully'], 200);
    }

    public function addLink(Request $request, ImpartedClass $ic)
    {
        $validator = Validator::make($request->all(), [
            'links_id' => 'nullable|array',
            'links_id.*' => 'exists:links,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $ic->links()->sync($request['links_id']);
        return response()->json(['message' => 'Imparted Class updated successfully'], 200);
    }

    public function syncStudent(Request $request, ImpartedClass $ic)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        if($ic->contrated_plan->students->contains('id',$request->student_id)){
            $ic->students()->syncWithoutDetaching([$request->student_id]);
            return response()->json(['message' => 'Imparted Class updated successfully'], 200);
        }else{
            return response()->json(['errors' => "Student invalid for this plan"], 422);
        }

    }


}
