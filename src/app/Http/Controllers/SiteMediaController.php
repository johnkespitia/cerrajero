<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SiteMediaController extends Controller
{
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'file' => 'required|image|max:5120',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $path = $request->file('file')->store('site', 'public');
        $url = Storage::disk('public')->url($path);

        return response([
            'path' => $path,
            'url' => $url,
        ], Response::HTTP_CREATED);
    }
}
