<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Skill;
use Illuminate\Support\Facades\Validator;

class SkillController extends Controller
{
    public function index()
    {
        $skills = Skill::all();
        return response()->json($skills, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:skills',
            // Add more validation rules as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $skill = Skill::create($validator->validated());

        return response()->json(['message' => 'Skill created successfully'], 201);
    }

    public function update(Request $request, Skill $skill)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:skills,name,' . $skill->id,
            // Add more validation rules as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $skill->update($validator->validated());

        return response()->json(['message' => 'Skill updated successfully'], 200);
    }

    public function destroy(Skill $skill)
    {
        $skill->delete();
        return response()->json(['message' => 'Skill deleted successfully'], 200);
    }
}
