<?php

namespace App\Http\Controllers;

use App\Models\Professor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;


class ProfessorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $professors = Professor::with('skills')->with("user")->get();
        return response()->json($professors, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Professor $professor): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hourly_fee' => 'required|numeric',
            'main_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'brief_resume' => 'required|string',
            'cv_url' => 'nullable|url',
            'email' => 'sometimes|required|email|unique:users,email,' . $professor->user->id, // Agregar más reglas si es necesario
            'name' => 'sometimes|min:6',
            'password' => 'sometimes|required|min:6', // Agregar más reglas si es necesario
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['email'])) {
            $professor->user->update([
                'email' => $data['email'],
            ]);
        }
        if (isset($data['password'])) {
            $professor->user->update([
                'password' => bcrypt($data['password']),
            ]);
        }
        if (isset($data['name'])) {
            $professor->user->update([
                'name' => $data['name'],
            ]);
        }
        if ($request->hasFile('main_photo')) {
            // Delete old photo if exists
            if ($professor->main_photo) {
                Storage::disk('public')->delete($professor->main_photo);
            }

            $mainPhotoPath = $request->file('main_photo')->store('professor_photos', 'public');
            $data['main_photo'] = Storage::url($mainPhotoPath);
        }

        $professor->update($data);

        if ($request->has('skills')) {
            $professor->skills()->sync($request->input('skills'));
        }

        return response()->json(['message' => 'Professor updated successfully'], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'legal_identification' => 'required|unique:professors',
            'hourly_fee' => 'required|numeric',
            'main_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'brief_resume' => 'required|string',
            'cv_url' => 'nullable|url',
            'email' => 'required|email|unique:users', // Agregar más reglas si es necesario
            'name' => 'required|min:6',
            'password' => 'required|min:6', // Agregar más reglas si es necesario
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        if ($request->hasFile('main_photo')) {
            $mainPhotoPath = $request->file('main_photo')->store('professor_photos', 'public');
            $data['main_photo'] = Storage::url($mainPhotoPath);
        }
        $user = User::create([
            'name' => $data['name'], // Puedes ajustar esto
            'email' => $data['email'],
            'password' => bcrypt($data['password']), // Puedes generar una contraseña aleatoria
        ]);

        $professorRole = Role::where('name', 'professor')->first();
        if ($professorRole) {
            $user->assignRole($professorRole);
        }

        $professor = $user->professor()->create($data);

        if ($request->has('skills')) {
            $professor->skills()->sync($request->input('skills'));
        }

        return response()->json(['message' => 'Professor created successfully'], 201);
    }


}
