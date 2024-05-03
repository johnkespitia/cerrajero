<?php

namespace App\Http\Controllers;

use App\Models\ImpartedClass;
use App\Models\ContratedPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\CalendarLinks\Link;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class ImpartedClassController extends Controller
{

    const DAYS_WEEK = [
        "SUNDAY",
        "MONDAY",
        "TUESDAY",
        "WEDNESDAY",
        "THURSDAY",
        "FRIDAY",
        "SATURDAY",
    ];

    public function massiveClassCreation(Request $request){
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|array',
            'days.*' => 'in:'.implode(self::DAYS_WEEK,','),
            'hours' => 'nullable|array',
            'hours.*' => 'date_format:H:i',
            'contrated_plan_id' => 'required|exists:contrated_plans,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ctdPlan = ContratedPlan::find($validator->validated()["contrated_plan_id"]);
        $ctdPlan->imparted_classes;
        $missingContratedHours = $ctdPlan->classes - $ctdPlan->imparted_classes->sum('class_duration');
        $startDate = Carbon::parse($ctdPlan->starting_date);
        $preparedRecordsData = [];
        // return response()->json(['errors' => $startDate->dayName], 422);
        $daysWeek = $validator->validated()["days"];
        $hoursWeek = $validator->validated()["hours"];

        $weeksCalculated = ceil($missingContratedHours/$ctdPlan->estimated_class_duration/sizeof($daysWeek));
        $dateNextClass = $startDate;

        for ($weekUsed=0; $weekUsed < $weeksCalculated; $weekUsed++) {
            if($missingContratedHours === 0 ){
                break;
            }
            foreach($daysWeek as $keyD => $day){
                $dateNextClass = $dateNextClass->next(array_search($day,self::DAYS_WEEK));
                if($missingContratedHours > 0 ){
                    $preparedRecordsData[] = [
                        'contrated_plan_id' => $ctdPlan->id,
                        'scheduled_class' => $dateNextClass->format('Y-m-d'),
                        'comments' => "",
                        'class_time' => $hoursWeek[$keyD],
                        'class_duration' => $ctdPlan->estimated_class_duration,
                        'professor_atendance' => false,
                        'class_closed'=>false
                    ];
                    $missingContratedHours -= $ctdPlan->estimated_class_duration;
                }
            }
        }
        try {
            $result = DB::table('imparted_classes')->insert($preparedRecordsData);
            return response()->json(['data' => $preparedRecordsData, "result"=>  $result], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contrated_plan_id' => 'required|exists:contrated_plans,id',
            'scheduled_class' => 'required|date',
            'comments' => 'min:3',
            'professor_atendance' => 'boolean',
            'class_time'=> 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $ctdPlan = ContratedPlan::find($validator->validated()["contrated_plan_id"]);
        $impartedClasses = sizeof($ctdPlan->imparted_classes);
        if(!empty($ctdPlan) && $ctdPlan->classes <= $impartedClasses){
            return response()->json(['errors' => "Can't create a new class, all classes assigned was created {$impartedClasses}"], 422);
        }
        $ctdPlan->students;
        $ctdPlan->professor;
        $ic = ImpartedClass::create($validator->validated());
        $endTimeClass = date('Y-m-d H:i', strtotime("{$ic->scheduled_class} {$ic->class_time}".'+1 hour'));
        $from = \DateTime::createFromFormat('Y-m-d H:i', "{$ic->scheduled_class} {$ic->class_time}");
        $to = \DateTime::createFromFormat('Y-m-d H:i', $endTimeClass);
        $link = Link::create("Clase #{$impartedClasses} con el profesor {$ctdPlan->professor->user->name}", $from, $to)
            ->description("Clase del plan {$ctdPlan->short_description} con el profesor  {$ctdPlan->professor->user->name} si deseas modificar la clase o tiienes alguna inquietud puedes comunicarte directamente con el profedor al correo  {$ctdPlan->professor->user->email}, puedes ingresar a través de nuestra plataforma https://dashboard.plgeducation.com/");
        $linkICS = $link->ics([
            'UID' => md5(substr($ctdPlan->short_description,0,10).".".$impartedClasses),
            'format' => 'file'
        ]);

        foreach ($ctdPlan->students as $student) {
            $data = [
                'bg' => asset('storage/mail_assets/mail-bg1.png'),
                'main_title' => "Clase agendada",
                'subtitle' => "Hemos agendado una clase de tu plan y continuar con tu proceso.",
                'main_btn_url' => "https://dashboard.plgeducation.com/",
                'main_btn_title' => "Ingresar a la platafoma",
                'plan' => $ctdPlan,
                'class' => $ic,
                "student"=> $student,
                "event_links" => [
                    "google" => $link->google(),
                    "yahoo" => $link->yahoo(),
                    "office" => $link->webOffice(),
                    "hotmail" => $link->webOutlook()
                ]
            ];
            Mail::send('email.scheduled-class-student', $data, function($message) use ($student, $linkICS){
                $message->to($student->user->email)->subject('Tienes una clase agendada en nuestra plaraforma :)');
                $message->attachData($linkICS, 'event.ics', [
                    'mime' => 'text/calendar',
                ]);
                $message->getSwiftMessage()->getHeaders()->addTextHeader('Content-class', 'urn:content-classes:calendarmessage');

            });
        }

        $emailsWithNames = $ctdPlan->students->map(function ($student) {
            return "{$student->user->name} ({$student->user->email})";
        })->implode("\n");

        $linkProfessor = Link::create("Clase #{$impartedClasses} del plan {$ctdPlan->short_description}", $from, $to)
            ->description("Clase del plan {$ctdPlan->short_description} con el/los estudiante(s): \n {$emailsWithNames}, puedes ingresar a través de nuestra plataforma https://dashboard.plgeducation.com/");
        $linkICS = $link->ics([
            'UID' => md5(substr($ctdPlan->short_description,0,10).".".$impartedClasses),
            'format' => 'file'
        ]);
        $data = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Clase agendada",
            'subtitle' => "Has completado el agendadamiento de una clase para el plan {$ctdPlan->short_description}.",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresar a la platafoma",
            'plan' => $ctdPlan,
            'class' => $ic,
            "event_links" => [
                "google" => $link->google(),
                "yahoo" => $link->yahoo(),
                "office" => $link->webOffice(),
                "hotmail" => $link->webOutlook()
            ]
        ];
        Mail::send('email.scheduled-class-professor', $data, function($message) use ($ctdPlan, $linkICS){
            $message->to($ctdPlan->professor->user->email)->subject('Has agendado una clase en la plaraforma :)');
            $message->attachData($linkICS, 'event.ics', [
                'mime' => 'text/calendar',
            ]);
            $message->getSwiftMessage()->getHeaders()->addTextHeader('Content-class', 'urn:content-classes:calendarmessage');

        });
        return response()->json(['message' => 'Imparted Class created successfully'], 201);
    }

    public function update(Request $request, ImpartedClass $ic)
    {
        $this->addCustomValidation();
        $validator = Validator::make($request->all(), [
            'contrated_plan_id' => 'exists:contrated_plans,id',
            'scheduled_class' => 'date',
            'comments' => 'min:3',
            'professor_atendance' => 'boolean',
            'class_time'=> 'date_format:H:i',
            'class_duration' => 'min:1|positive_decimal',
            'class_closed' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if(!empty($data["professor_atendance"])){
            $user = Auth::user();
            $user->professor;
            $data["professor_id"] = $user->professor->id;
        }

        $ic->update($data);

        if($data['class_closed'] && $data['class_duration']>0){
            if($ic->professor_atendance
            // && $ic->students_attendance->count() > 0
            ){
                $ic->contrated_plan->taked_classes += $data['class_duration'];
                $ic->contrated_plan->save();
            }
        }



        return response()->json(['message' => 'Imparted Class updated successfully'], 200);
    }

    public function addLink(Request $request, ImpartedClass $ic)
    {
        $validator = Validator::make($request->all(), [
            'links_id' => 'nullable|array',
            'links_id.*' => 'exists:links,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $ic->links()->sync($request['links_id']);
        return response()->json(['message' => 'Imparted Class updated successfully'], 200);
    }

    public function syncStudent(Request $request, ImpartedClass $ic)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        if($ic->contrated_plan->students->contains('id',$request->student_id)){
            $ic->students_attendance()->syncWithoutDetaching([$request->student_id]);
            if(($ic->contrated_plan->classes - $ic->contrated_plan->estimated_class_duration) <= $ic->contrated_plan->taked_classes){
                foreach ($ctdPlan->students as $student) {
                    $data = [
                        'bg' => asset('storage/mail_assets/mail-bg1.png'),
                        'main_title' => "Te queda una clase por tomar",
                        'subtitle' => "Ya casi termina tu plan, no dejes que se detenga tu progreso y adquiere un nuevo ciclo.",
                        'main_btn_url' => "https://dashboard.plgeducation.com/",
                        'main_btn_title' => "Ingresar a la platafoma",
                        'plan' => $ctdPlan,
                        'class' => $ic,
                        "student"=> $student,
                    ];
                    Mail::send('email.last-class-student', $data, function($message) use ($student, $linkICS){
                        $message->to($student->user->email)->subject('Se te está acabando el plan :o');
                        $message->attachData($linkICS, 'event.ics', [
                            'mime' => 'text/calendar',
                        ]);
                        $message->getSwiftMessage()->getHeaders()->addTextHeader('Content-class', 'urn:content-classes:calendarmessage');

                    });
                }
            }
            if($ic->contrated_plan->classes === $ic->contrated_plan->taked_classes){
                foreach ($ctdPlan->students as $student) {
                    $data = [
                        'bg' => asset('storage/mail_assets/mail-bg1.png'),
                        'main_title' => "Se ha terminado el plan",
                        'subtitle' => "No dejes que se detenga tu progreso y adquiere un nuevo ciclo.",
                        'main_btn_url' => "https://dashboard.plgeducation.com/",
                        'main_btn_title' => "Ingresar a la platafoma",
                        'plan' => $ctdPlan,
                        'class' => $ic,
                        "student"=> $student,
                    ];
                    Mail::send('email.end-plan-student', $data, function($message) use ($student, $linkICS){
                        $message->to($student->user->email)->subject('Se acabó tu plan pero no tu proceso :)');
                    });
                }
            }
            return response()->json(['message' => 'Imparted Class updated successfully', 'data' => $ic->students_attendance ], 200);
        }else{
            return response()->json(['errors' => "Student invalid for this plan"], 422);
        }

    }


}
