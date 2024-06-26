<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\Professor;
use App\Models\Student;
use App\Models\ContratedPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContratedPlanController extends Controller
{

    public function list(Request $request){

        $cps = ContratedPlan::with("professor.user")
        ->with("students.user")
        ->with("tags")
        ->with("substitutes.professor.user")
        ->with("imparted_classes.links")
        ->with("imparted_classes.students_attendance.user")
        ->with("imparted_classes.atended_professor.user")
        ->get();
        return response($cps, Response::HTTP_OK);
    }
    public function filteredList(Request $request, Professor $professor){
        $cps = ContratedPlan::with("professor.user.links")
                ->with("students.user.links")
                ->with("imparted_classes.links")
                ->with("imparted_classes.students_attendance.user")
                ->with("imparted_classes.atended_professor.user")
                ->with("tags")
                ->where('professor_id','=',$professor->id)
                ->orderBy('expiration_date', 'DESC')
                ->orderByRaw('(classes - taked_classes) DESC')
                ->get();
        return response($cps, Response::HTTP_OK);
    }

    public function filteredByStudentList(Request $request, Student $student){
        $cps = $student->contrated_plans()->with([
            "professor.skills",
            "professor.user.links",
            "imparted_classes.links",
            "imparted_classes.students_attendance.user",
            "imparted_classes.atended_professor.user",
            "substitutes.professor.user",
            "substitutes.professor.skills",
            "students.user",
            "tags"
        ])->orderBy('expiration_date', 'DESC')
        ->orderByRaw('(classes - taked_classes) DESC')
        ->get();
        return response($cps, Response::HTTP_OK);
    }

    public function create(Request $request){
        $this->addCustomValidation();
        $validationRules = [
            'starting_date' => 'required|date',
            'expiration_date' => 'required|date|after:starting_date',
            'short_description' => 'required|max:200',
            'plan_extra_details' => 'required',
            'classes' => 'required|min:1|positive_decimal',
            'taked_classes' => 'min:0|lte:classes|positive_decimal',
            'estimated_class_duration' => 'min:0|lte:classes|positive_decimal',
            'professor_id' => 'integer|exists:professors,id',
            'hourly_fee' => 'numeric|min:0'
        ];
        if($request->has('webhook')){
            $validation = Validator::make($request->get("customData"),$validationRules);
        }else{
            $validation = Validator::make($request->all(), $validationRules);
        }
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cplan = ContratedPlan::create($validation->validated());
        if(!empty( $cplan->professor)){
            $cplan->professor->user;

            $data = [
                'bg' => asset('storage/mail_assets/mail-bg2.png'),
                'main_title' => "Te hemos asignado un plan de clases",
                'subtitle' => "Ya puedes agendar las clases clases, a continuación encontraras detalles del plan",
                'main_btn_url' => "https://dashboard.plgeducation.com/",
                'main_btn_title' => "Ingresa a la platafoma",
                'plan' => $cplan
            ];

            Mail::send('email.created-plan-professor', $data, function($message) use ($cplan){
                $message->to($cplan->professor->user->email)->subject('Tu plan de clases en PLG ha sido creado');
            });
        }

        return response(['msg' => "Plan saved", 'rol'=>$cplan], Response::HTTP_OK);
    }

    public function update(Request $request, ContratedPlan $cplan)
    {
        $this->addCustomValidation();
        $validator = Validator::make($request->all(), [
            'starting_date' => 'date',
            'expiration_date' => 'date|after:starting_date',
            'short_description' => 'max:200',
            'classes' => 'min:1|positive_decimal',
            'taked_classes' => 'min:0|lte:classes|positive_decimal',
            'estimated_class_duration' => 'min:0|lte:classes|positive_decimal',
            'professor_id' => 'integer|exists:professors,id',
            'hourly_fee' => 'numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cplan->update($validator->validated());
        if(!empty( $cplan->professor)){
            $cplan->professor->user;

            $data = [
                'bg' => asset('storage/mail_assets/mail-bg2.png'),
                'main_title' => "Te hemos asignado un plan de clases",
                'subtitle' => "Ya puedes agendar las clases clases, a continuación encontraras detalles del plan",
                'main_btn_url' => "https://dashboard.plgeducation.com/",
                'main_btn_title' => "Ingresa a la platafoma",
                'plan' => $cplan
            ];

            Mail::send('email.created-plan-professor', $data, function($message) use ($cplan){
                $message->to($cplan->professor->user->email)->subject('Tu plan de clases en PLG ha sido creado');
            });
        }
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
        $cplan->professor->user;
        foreach ($request->input('students') as $std) {
            $student = Student::find($std);
            $data = [
                'bg' => asset('storage/mail_assets/mail-bg2.png'),
                'main_title' => "Tu plan ha sido asignado ",
                'subtitle' => "Ya puedes acceder a iniciar tus clases, a continuación encontraras detalles del plan y el profesor",
                'main_btn_url' => "https://dashboard.plgeducation.com/",
                'main_btn_title' => "Ingresa a la platafoma",
                'plan' => $cplan,
                'student' => $student
            ];
            $student->user;
            Mail::send('email.created-plan-student', $data, function($message) use ($student){
                $message->to($student->user->email)->subject('Tu plan de clases en PLG ha sido creado');
            });
        }
        return response()->json(['message' => 'students added successfully'], 200);
    }

    public function addTags(Request $request, ContratedPlan $cplan)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $cplan->tags()->sync($request->input('tags'));
        return response()->json(['message' => 'tags added successfully'], 200);
    }

}
