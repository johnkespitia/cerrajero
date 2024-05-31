<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Mail;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::with("user")->with('user.links')->get();
        return response()->json($students, 200);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validatorRules=[
            'legal_identification' => 'required|unique:students',
            'main_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'required|email|unique:users', // Agregar más reglas si es necesario
            'name' => 'required|min:6',
            'password' => 'min:6', // Agregar más reglas si es necesario
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

        if(empty($data['password'])){
            $data['password']="STD{$data['legal_identification']}@!";
        }

        if ($request->hasFile('main_photo')) {
            $mainPhotoPath = $request->file('main_photo')->store('student_photos', 'public');
            $data['main_photo'] = Storage::url($mainPhotoPath);
        }
        $user = User::create([
            'name' => $data['name'], // Puedes ajustar esto
            'email' => $data['email'],
            'password' => bcrypt($data['password']), // Puedes generar una contraseña aleatoria
        ]);

        $studentRole = Role::where('name', 'student')->first();
        if ($studentRole) {
            $user->assignRole($studentRole);
        }

        $student = $user->student()->create($data);

        $data = [
            'bg' => asset('storage/mail_assets/mail-bg8.png'),
            'main_title' => "Bienvenido Estudiante",
            'subtitle' => "Tu cuenta ha sido activada",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresa a la platafoma",
            'student' => $student
          ];

          Mail::send('email.welcome-student', $data, function($message) use ($student){
            $message->to($student->user->email)->subject('Bienvenido a PLG');
          });
        return response()->json(['message' => 'Student created successfully'], 201);
    }

    public function updateImage(Request $request, Student $student): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'main_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if ($request->hasFile('main_photo')) {
            if ($student->main_photo) {
                $path=parse_url($student->main_photo);
                Storage::disk('public')->delete(substr($path['path'], 1));
            }
            $mainPhotoPath = $request->file('main_photo')->store('student_photos', 'public');
            $data['main_photo'] = env("APP_URL").Storage::url($mainPhotoPath);
            $student->update($data);

            return response()->json(['message' => 'Student photo updated successfully'], 200);
        }else{
            return response()->json(['message' => 'Student photo can\'t updated successfully'], 500);
        }
    }

    public function update(Request $request, Student $student): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'legal_identification' => 'required|unique:students,legal_identification,'.$student->id,
            'main_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'required|email|unique:users,email,'.$student->user->id, // Agregar más reglas si es necesario
            'name' => 'required|min:6',
            'password' => 'min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['email'])) {
            $student->user->update([
                'email' => $data['email'],
            ]);
        }
        if (isset($data['password'])) {
            $student->user->update([
                'password' => bcrypt($data['password']),
            ]);
        }
        if (isset($data['name'])) {
            $student->user->update([
                'name' => $data['name'],
            ]);
        }
        if ($request->hasFile('main_photo')) {
            if ($student->main_photo) {
                Storage::disk('public')->delete($student->main_photo);
            }

            $mainPhotoPath = $request->file('main_photo')->store('professor_photos', 'public');
            $data['main_photo'] = Storage::url($mainPhotoPath);
        }

        $student->update($data);

        return response()->json(['message' => 'Professor updated successfully'], 200);
    }
}
