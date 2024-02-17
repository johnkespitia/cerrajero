<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Links;
use App\Models\Users;

class LinksController extends Controller
{
    public function index()
    {
        $links = Links::all();
        return response()->json($links, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:meet,classroom,extra_content',
            'link' => 'required|url',
            'active' => 'required|boolean',
            'user_id' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $skill = Links::create($validator->validated());

        return response()->json(['message' => 'Link created successfully'], 201);
    }

    public function update(Request $request, Links $link)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:meet,classroom,extra_content',
            'link' => 'required|url',
            'active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $link->update($validator->validated());

        return response()->json(['message' => 'link updated successfully'], 200);
    }

    public function destroy(Links $link)
    {
        $link->delete();
        return response()->json(['message' => 'Link deleted successfully'], 200);
    }
}
