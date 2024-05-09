<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticClass;
use App\Models\Professor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\CalendarLinks\Link;
use Illuminate\Support\Facades\Mail;

class DiagnosticClassController extends Controller
{
    public function index()
    {
        $cps = DiagnosticClass::with("professor.user.links")
                ->orderBy('starting_date', 'DESC')
                ->orderBy('class_closed','ASC')
                ->get();
        return response()->json($cps, 200);
    }

    public function indexProfessor(Professor $professor)
    {
        $cps = DiagnosticClass::with("professor.user.links")
                ->where('professor_id','=',$professor->id)
                ->orderBy('starting_date', 'DESC')
                ->orderBy('class_closed', 'ASC')
                ->get();
        return response()->json($cps, 200);
    }

    public function indexCandidate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'candidate_email' => 'required|email|exists:diagnostic_classes,candidate_email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $diagClass = DiagnosticClass::with("professor.user.links")->where('class_closed',false)->where('candidate_email',$validator->validated()['candidate_email'])->first();
        return response()->json($diagClass, 200);
    }

    public function attendCandidate(Request $request, DiagnosticClass $dg)
    {
        $dg->professor->user->links;
        $dg->candidate_attended = true;
        $dg->save();
        return response()->json($dg, 200);
    }

    public function store(Request $request)
    {
        $this->addCustomValidation();
        $validator = Validator::make($request->all(), [
            'starting_date' => 'required|date',
            'starting_time' => 'required|date_format:H:i',
            'candidate_name' => 'required|min:5',
            'candidate_email' => 'required|email',
            'class_duration' => 'positive_decimal|min:1',
            'class_closed' => 'boolean',
            'comments' => 'min:0',
            'professor_id' => 'required|exists:professors,id',
            'candidate_attended' => 'boolean',
            'hourly_fee' => 'numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $diagClass = DiagnosticClass::create($validator->validated());
        $diagClass->professor;
        $endTimeClass = date('Y-m-d H:i', strtotime("{$diagClass->starting_date} {$diagClass->starting_time}".'+'.$diagClass->class_duration.' hour'));
        $from = \DateTime::createFromFormat('Y-m-d H:i', "{$diagClass->starting_date} {$diagClass->starting_time}");
        $to = \DateTime::createFromFormat('Y-m-d H:i', $endTimeClass);
        $link = Link::create("Clase Diagnistica con el profesor {$diagClass->professor->user->name}", $from, $to)
            ->description("Clase de diagnostico con el profesor  {$diagClass->professor->user->name} si deseas modificar la clase o tienes alguna inquietud puedes comunicarte directamente con el profedor al correo  {$diagClass->professor->user->email}, puedes ingresar a través de nuestra plataforma https://dashboard.plgeducation.com/diagnostic-class");
        $linkICS = $link->ics([
            'UID' => md5($diagClass->candidate_email.".".$diagClass->starting_date),
            'format' => 'file'
        ]);

        $linkProf = Link::create("Clase Diagnistica con el estudiante {$diagClass->candidate_name}", $from, $to)
            ->description("Clase de diagnostico con el estudiante  {$diagClass->candidate_name} si deseas modificar la clase o tienes alguna inquietud puedes comunicarte directamente con el estudiante al correo  {$diagClass->candidate_email}, puedes ingresar a través de nuestra plataforma https://dashboard.plgeducation.com");
        $linkICSProf = $linkProf->ics([
            'UID' => md5($diagClass->candidate_email.".".$diagClass->starting_date),
            'format' => 'file'
        ]);

        $data = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Clase Diagnostico agendada",
            'subtitle' => "Hemos agendado una clase de diagnistico para conocer tu proceso.",
            'main_btn_url' => "https://dashboard.plgeducation.com/diagnostic-class",
            'main_btn_title' => "Ingresar a la platafoma",
            'class' => $diagClass,
            "event_links" => [
                "google" => $link->google(),
                "yahoo" => $link->yahoo(),
                "office" => $link->webOffice(),
                "hotmail" => $link->webOutlook()
            ]
        ];
        $dataP = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Clase Diagnostico agendada",
            'subtitle' => "Hemos agendado una clase de diagnistico.",
            'main_btn_url' => "https://dashboard.plgeducation.com",
            'main_btn_title' => "Ingresar a la platafoma",
            'class' => $diagClass,
            "event_links" => [
                "google" => $linkProf->google(),
                "yahoo" => $linkProf->yahoo(),
                "office" => $linkProf->webOffice(),
                "hotmail" => $linkProf->webOutlook()
            ]
        ];
        Mail::send('email.scheduled-diagnostic-class-student', $data, function($message) use ($diagClass, $linkICS){
            $message->to($diagClass->candidate_email)->subject('Tienes una clase de diagnostico agendada en nuestra plaraforma :)');
            $message->attachData($linkICS, 'event.ics', [
                'mime' => 'text/calendar',
            ]);
            $message->getSwiftMessage()->getHeaders()->addTextHeader('Content-class', 'urn:content-classes:calendarmessage');

        });
        Mail::send('email.scheduled-diagnostic-class-professor', $dataP, function($message) use ($diagClass, $linkICSProf){
            $message->to($diagClass->professor->user->email)->subject('Tienes una clase de diagnostico agendada en nuestra plaraforma :)');
            $message->attachData($linkICSProf, 'event.ics', [
                'mime' => 'text/calendar',
            ]);
            $message->getSwiftMessage()->getHeaders()->addTextHeader('Content-class', 'urn:content-classes:calendarmessage');

        });

        return response()->json(['message' => 'Diagnostic Class created successfully'], 201);
    }

    public function update(Request $request, DiagnosticClass $diagClass)
    {
        $this->addCustomValidation();
        $validator = Validator::make($request->all(), [
            'starting_date' => 'date',
            'starting_time' => 'date_format:H:i',
            'candidate_name' => 'min:5',
            'candidate_email' => 'email',
            'class_duration' => 'positive_decimal|min:1',
            'class_closed' => 'boolean',
            'comments' => 'min:0',
            'professor_id' => 'exists:professors,id',
            'candidate_attended' => 'boolean',
            'hourly_fee' => 'numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $diagClass->update($validator->validated());

        return response()->json(['message' => 'Diagnostic Class updated successfully'], 200);
    }

    public function destroy(DiagnosticClass $diagClass)
    {
        $diagClass->delete();
        return response()->json(['message' => 'Diagnostic Class deleted successfully'], 200);
    }
}
