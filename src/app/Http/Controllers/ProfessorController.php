<?php

namespace App\Http\Controllers;

use App\Models\Professor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Mail;


class ProfessorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $professors = Professor::with('skills')->with("user")->with('user.links')->get();
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
            'hourly_fee' => 'numeric',
            'main_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'brief_resume' => 'string',
            'cv_url' => 'nullable|url',
            'email' => 'sometimes|email|unique:users,email,' . $professor->user->id,
            'name' => 'sometimes|min:6',
            'password' => 'sometimes|min:6',
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

        $professor->update($data);

        if ($request->has('skills')) {
            $professor->skills()->sync($request->input('skills'));
        }

        return response()->json(['message' => 'Professor updated successfully'], 200);
    }

    public function updateImage(Request $request, Professor $professor): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'main_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if ($request->hasFile('main_photo')) {
            if ($professor->main_photo) {
                $path=parse_url($professor->main_photo);
                Storage::disk('public')->delete(substr($path['path'], 1));
            }
            $mainPhotoPath = $request->file('main_photo')->store('professor_photos', 'public');
            $data['main_photo'] = env("APP_URL").Storage::url($mainPhotoPath);
            $professor->update($data);

            return response()->json(['message' => 'Professor photo updated successfully'], 200);
        }else{
            return response()->json(['message' => 'Professor photo can\'t updated successfully'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validatorRules=[
            'legal_identification' => 'required|unique:professors',
            'hourly_fee' => 'numeric',
            'main_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'brief_resume' => 'required|string',
            'cv_url' => 'nullable|url',
            'email' => 'required|email|unique:users',
            'name' => 'required|min:6',
            'password' => 'sometimes|min:6',
            'skills' => 'nullable|array',
            'skills.*' => 'exists:skills,id',
        ];
        if($request->has('webhook')){
           $validator = Validator::make($request->get("customData"),$validatorRules);
        }else{
            $validator = Validator::make($request->all(), $validatorRules);
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        if ($request->hasFile('main_photo')) {
            $mainPhotoPath = $request->file('main_photo')->store('professor_photos', 'public');
            $data['main_photo'] = Storage::url($mainPhotoPath);
        }

        if(empty($data['password'])){
            $data['password']="PFS{$data['legal_identification']}@!";
        }
        if(empty($data['hourly_fee'])){
            $data['hourly_fee']=18500;
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
        $data = [
            'bg' => asset('storage/mail_assets/mail-bg6.png'),
            'main_title' => "Bienvenido Profesor",
            'subtitle' => "Tu cuenta ha sido activada",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresa a la platafoma",
            'professor' => $professor
          ];

          Mail::send('email.welcome-professor', $data, function($message) use ($professor){
            $message->to($professor->user->email)->subject('Bienvenido Profesor a PLG');
          });

        return response()->json(['message' => 'Professor created successfully'], 201);
    }


}
