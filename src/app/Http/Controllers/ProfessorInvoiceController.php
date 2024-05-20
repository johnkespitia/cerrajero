<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\Professor;
use App\Models\ProfessorInvoice;
use App\Models\ImpartedClass;
use App\Models\DiagnosticClass;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Services\PDFGeneratorService;

class ProfessorInvoiceController extends Controller
{
    private $clientPDFGeneratorService;

    public function __construct(PDFGeneratorService $clientPDFGeneratorService)
    {
        $this->clientPDFGeneratorService = $clientPDFGeneratorService;
    }

    public function listAll(Request $request){
        $invoicesBuilder = ProfessorInvoice::orderBy('generation_time', 'DESC')
            ->orderBy('start_date', 'DESC')
            ->with("professor.user")
            ->with("diagnostic_class")
            ->with("imparted_class.students_attendance.user")
            ->with("imparted_class.contrated_plan");

        if($request->has('sent')){
            $invoicesBuilder->where("sent", true);
        }
        if($request->has('approved')){
            $invoicesBuilder->where("approved", true);
        }
        if($request->has('payed')){
            $invoicesBuilder->where("payed", true);
        }
        if($request->has('professor')){
            $invoicesBuilder->where("professor_id", $request->query('professor'));
        }

        $listInvoices= $invoicesBuilder->get();
        return response($listInvoices, Response::HTTP_OK);
    }

    public function listByProfessor(Request $request, Professor $professor){
        $listInvoices = ProfessorInvoice::where("professor_id", $professor->id)
            ->orderBy('generation_time', 'DESC')
            ->orderBy('start_date', 'DESC')
            ->with("professor.user")
            ->with("diagnostic_class")
            ->with("imparted_class.students_attendance.user")
            ->with("imparted_class.contrated_plan")
            ->get();
        return response($listInvoices, Response::HTTP_OK);
    }

    public function previewClasses(Request $request, Professor $professor, $start_date, $end_date){
        $builderClasses = ImpartedClass::whereBetween('scheduled_class', [$start_date, $end_date])
            ->where("professor_id",$professor->id)
            ->where("class_closed", true)
            ->whereNull("professor_invoice_id");

        $builderDiagnosticClasses = DiagnosticClass::whereBetween('starting_date', [$start_date, $end_date])
            ->where("professor_id",$professor->id)
            ->where("candidate_attended", true)
            ->whereNull("professor_invoice_id");
        $listRecords = $builderClasses->get();
        $listDiagnostic = $builderDiagnosticClasses->get();
        $listRecords->load("contrated_plan");
        return response()->json(['message' => 'Data listed successfully', "data" => [ "plan_classes"=>$listRecords,"diagnostic_classes"=>$listDiagnostic]], 200);
    }

    public function store(Request $request){
        $validation = Validator::make($request->all(), [
            'generation_time' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'professor_id' => 'integer|exists:professors,id',
            'comments' => 'max:250',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = $validation->validated();
        $builderClasses = ImpartedClass::whereBetween('scheduled_class', [$data["start_date"], $data["end_date"]])
            ->where("professor_id",$data["professor_id"])
            ->where("class_closed", true);

        $builderDiagnosticClasses = DiagnosticClass::whereBetween('starting_date', [$data["start_date"], $data["end_date"]])
            ->where("professor_id",$data["professor_id"])
            ->where("candidate_attended", true)
            ->where("class_closed", true);
        $listRecords = $builderClasses->get();
        $listDiagnostic = $builderDiagnosticClasses->get();
        if($listRecords->count() === 0 && $listDiagnostic->count() === 0){
            return response()->json(['message' => 'Imparted classes not found'], 500);
        }
        $listRecords->load("contrated_plan");
        $total_value = 0;
        $total_time = 0;
        foreach($listRecords as $record){
           $total_value += $record->class_duration * $record->contrated_plan->hourly_fee;
           $total_time += $record->class_duration;
        }
        foreach($listDiagnostic as $record){
            $total_value += $record->class_duration * $record->hourly_fee;
            $total_time += $record->class_duration;
         }
        $data["total_time"] = $total_time;
        $data["total_value"] = $total_value;
        $newInvoice = ProfessorInvoice::create($data);

        $builderClasses->update(['professor_invoice_id' => $newInvoice->id]);
        $builderDiagnosticClasses->update(['professor_invoice_id' => $newInvoice->id]);
        return response()->json(['message' => 'Invoice created successfully', "data"=>$newInvoice], 200);
    }

    public function update(Request $request, ProfessorInvoice $pinvoice){
        $this->addCustomValidation();
        $validation = Validator::make($request->all(), [
            'generation_time' => 'date',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'comments' => 'max:250',
            'sent' => 'boolean',
            'approved' => 'boolean',
            'payed' => 'boolean',
            'recalculate' => 'boolean',
            'signature_img' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        if ($validation->fails()) {return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $data = $validation->validated();
        if(!empty($data['recalculate'])){
            $builderClasses = ImpartedClass::whereBetween('scheduled_class', [$data["start_date"], $data["end_date"]])
                ->where("class_closed", true)
                ->where("professor_id",$pinvoice->professor_id);

            $builderDiagnosticClasses = DiagnosticClass::whereBetween('starting_date', [$data["start_date"], $data["end_date"]])
                ->where("professor_id",$pinvoice->professor_id)
                ->where("candidate_attended", true)
                ->where("class_closed", true);

            $listRecords = $builderClasses->get();
            $listDiagnostic = $builderDiagnosticClasses->get();
            $listRecords->load("contrated_plan");
            $total_value = 0;
            $total_time = 0;
            foreach($listRecords as $record){
               $total_value += $record->class_duration * $record->contrated_plan->hourly_fee;
               $total_time += $record->class_duration;
            }
            foreach($listDiagnostic as $record){
                $total_value += $record->class_duration * $record->hourly_fee;
                $total_time += $record->class_duration;
             }
            $data["total_time"] = $total_time;
            $data["total_value"] = $total_value;
            $builderClasses->update(['professor_invoice_id' => $pinvoice->id]);
            $builderDiagnosticClasses->update(['professor_invoice_id' => $pinvoice->id]);
        }

        if(!empty($data['sent'])){
            $data["sent_date"] = date('Y-m-d');
            if ($request->hasFile('signature_img')) {
                if ($pinvoice->signature_img) {
                    $path=parse_url($pinvoice->signature_img);
                    Storage::disk('public')->delete(substr($path['path'], 1));
                }
                $mainPhotoPath = $request->file('signature_img')->store('professor_signatures', 'public');
                $data['signature_img'] = env("APP_URL").Storage::url($mainPhotoPath);
            }else{
                return response()->json(['message' => 'Signature can\'t updated successfully'], 500);
            }
        }
        if(!empty($data['approved'])){
            $data["approved_date"] = date('Y-m-d');
        }
        if(!empty($data['payed'])){
            $data["payed_date"] = date('Y-m-d');
        }

        $pinvoice->update($data);
        if(!empty($data['sent'])){
            $this->emailSendInvoice($pinvoice);
        }
        if(!empty($data['approved'])){
            $this->emailApproveInvoice($pinvoice);
        }
        if(!empty($data['payed'])){
            $this->emailPayedInvoice($pinvoice);
        }
        return response()->json(['message' => 'invoice updated successfully', "data"=>$data], 200);
    }

    public function delete(Request $request, ProfessorInvoice $pinvoice){
        $builderClasses = ImpartedClass::where("professor_invoice_id", $pinvoice->id);
        $builderDiagnosticClasses = DiagnosticClass::where("professor_invoice_id", $pinvoice->id);
        $builderClasses->update(['professor_invoice_id' => null]);
        $builderDiagnosticClasses->update(['professor_invoice_id' => null]);
        $pinvoice->delete();
        return response()->json(['message' => 'invoice deleted successfully'], 200);
    }

    public function generatePDF(Request $request, ProfessorInvoice $pinvoice) {
        $pinvoice->load("professor.user");
        $pinvoice->load("diagnostic_class");
        $pinvoice->load("imparted_class.contrated_plan");
        return  $this->clientPDFGeneratorService->generatePDF($pinvoice);
    }
    private function emailSendInvoice(ProfessorInvoice $invoice) {
        $invoice->load("professor.user");
        $data = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Cuenta de cobro enviada",
            'subtitle' => "Se ha realizado el envío de la cuenta de cobro del profesor y está lista para aprobación",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresar a la platafoma",
            'invoice' => $invoice
          ];

          Mail::send('email.invoice-professor', $data, function($message) use ($invoice){
            $message->to(env("EMAIL_NOTIFICATION_URL"))->subject('Cuenta de cobro enviada');
          });
    }

    private function emailApproveInvoice(ProfessorInvoice $invoice) {
        $invoice->load("professor.user");
        $data = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Cuenta de cobro aprobada",
            'subtitle' => "Se ha aprobado la cuenta de cobro y está pendiente para pago.",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresar a la platafoma",
            'invoice' => $invoice
          ];

          Mail::send('email.invoice-approved', $data, function($message) use ($invoice){
            $message->to($invoice->professor->user->email)->subject('Cuenta de cobro aprobada!');
          });
    }

    private function emailPayedInvoice(ProfessorInvoice $invoice) {
        $invoice->load("professor.user");
        $data = [
            'bg' => asset('storage/mail_assets/mail-bg1.png'),
            'main_title' => "Cuenta de cobro pagada",
            'subtitle' => "Se ha realizado el pago de la cuenta de cobro.",
            'main_btn_url' => "https://dashboard.plgeducation.com/",
            'main_btn_title' => "Ingresar a la platafoma",
            'invoice' => $invoice
          ];

          Mail::send('email.invoice-payed', $data, function($message) use ($invoice){
            $message->to($invoice->professor->user->email)->subject('Cuenta de cobro pagada!');
          });
    }
}
