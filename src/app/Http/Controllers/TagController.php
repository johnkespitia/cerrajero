<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tag;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::all();
        return response()->json($tags, 200);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tag' => 'required|unique:tags',
            // Add more validation rules as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag = Tag::create($validator->validated());

        return response()->json(['message' => 'tag created successfully'], 201);
    }

    public function update(Request $request, Tag $tag)
    {
        $validator = Validator::make($request->all(), [
            'tag' => 'required|unique:tags,tag,' . $tag->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag->update($validator->validated());

        return response()->json(['message' => 'tag updated successfully'], 200);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return response()->json(['message' => 'tag deleted successfully'], 200);
    }
}
