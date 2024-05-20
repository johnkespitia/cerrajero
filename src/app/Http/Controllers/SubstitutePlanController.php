<?php

namespace App\Http\Controllers;
use App\Models\ContratedPlan;
use App\Models\SubstitutePlan;
use App\Models\Professor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class SubstitutePlanController extends Controller
{
    public function filteredList(Request $request, Professor $professor){
        $cps = SubstitutePlan::with("professor.user.links")
                ->with("contrated_plan.students.user.links")
                ->with("contrated_plan.imparted_classes.links")
                ->with("contrated_plan.imparted_classes.students_attendance.user")
                ->with("contrated_plan.tags")
                ->where('professor_id','=',$professor->id)
                ->orderBy('end_date', 'DESC')
                ->get();
        return response($cps, Response::HTTP_OK);
    }

    public function list(Request $request, Professor $professor){
        $cps = SubstitutePlan::with("professor.user.links")
                ->with("contrated_plan.students.user.links")
                ->with("contrated_plan.imparted_classes.links")
                ->with("contrated_plan.imparted_classes.students_attendance.user")
                ->with("contrated_plan.tags")
                ->orderBy('contrated_plan.expiration_date', 'DESC')
                ->orderByRaw('(contrated_plan.classes - contrated_plan.taked_classes) DESC')
                ->get();
        return response($cps, Response::HTTP_OK);
    }

    public function create(Request $request){

        $validation = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:starting_date',
            'professor_id' => 'integer|exists:professors,id',
            'contrated_plan_id' => 'integer|exists:contrated_plans,id'
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $cplan = SubstitutePlan::create($validation->validated());
        $cplan->professor->user;

        $data = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Tienes un plan asignado como sustituto",
            'subtitle' => "Ya puedes agendar las clases clases, a continuación encontraras detalles del plan",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresa a la platafoma",
            'plan' => $cplan
        ];

        Mail::send('email.substitute-plan-professor', $data, function($message) use ($cplan){
            $message->to($cplan->professor->user->email)->subject('Has sido asignado a un plan como sustituto');
        });

        return response(['msg' => "Substitute Plan saved", 'rol'=>$cplan], Response::HTTP_OK);
    }

    public function update(Request $request, SubstitutePlan $substitute)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:starting_date',
            'professor_id' => 'integer|exists:professors,id',
            'contrated_plan_id' => 'integer|exists:contrated_plans,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $substitute->update($validator->validated());
        return response()->json(['message' => 'Substitute Plan updated successfully'], 200);
    }
}
