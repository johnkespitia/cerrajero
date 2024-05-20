<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/diagnostic-class-access', [\App\Http\Controllers\DiagnosticClassController::class, 'indexCandidate']);
Route::get('/diagnostic-class-attend/{dg}', [\App\Http\Controllers\DiagnosticClassController::class, 'attendCandidate']);
Route::post('/login', [\App\Http\Controllers\UserController::class, 'apiLogin']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::controller(\App\Http\Controllers\RolController::class)->group(function () {
        Route::get('/rol', 'list')->middleware('permission:rol.edit,cerrajero');
        Route::get('/rol/{rol}', 'show')->middleware('permission:rol.edit,cerrajero');
        Route::post('/rol', 'save')->middleware('permission:rol.edit,cerrajero');
        Route::put('/rol/{rol}', 'update')->middleware('permission:rol.edit,cerrajero');
        Route::post('/rol/grant-permission/{rol}', 'grantPermission')->middleware('permission:rol.grantpermission,cerrajero');
        Route::post('/rol/revoke-permission/{rol}', 'revokePermission')->middleware('permission:rol.revokepermission,cerrajero');
    });
    Route::controller(\App\Http\Controllers\PermissionsController::class)->group(function () {
        Route::get('/permission', 'list')->middleware('permission:permission.edit,cerrajero');
        Route::get('/permission/{permission}', 'show')->middleware('permission:permission.edit,cerrajero');
        Route::post('/permission', 'save')->middleware('permission:permission.edit,cerrajero');
        Route::put('/permission/{permission}', 'update')->middleware('permission:permission.edit,cerrajero');
    });
    Route::controller(\App\Http\Controllers\UserController::class)->group(function () {
        Route::get('/accounts', 'list')->middleware('permission:user.list,cerrajero');
        Route::get('/accounts/{user}', 'show')->middleware('permission:user.list,cerrajero');
        Route::post('/accounts', 'save')->middleware('permission:user.create,cerrajero');
        Route::put('/accounts/{user}', 'update')->middleware('permission:user.edit,cerrajero');
        Route::post('/accounts/role/{user}', 'assignRole')->middleware('permission:user.edit,cerrajero');
        Route::delete('/accounts/role/{user}/{rol}', 'removeRole')->middleware('permission:user.edit,cerrajero');
        Route::post('/accounts/superior/{user}', 'assignSuperior')->middleware('permission:user.edit,cerrajero');
        Route::delete('/accounts/superior/{user}/{superior}', 'removeSuperior')->middleware('permission:user.edit,cerrajero');
        Route::get('/my-account', 'mydata');
        Route::get('/can-i/{guard}/{permission}', 'cani');
    });
    Route::controller(\App\Http\Controllers\GuardController::class)->group(function () {
        Route::get('/guard', 'list')->middleware('permission:guard.list,cerrajero');
        Route::get('/guard/{guard}', 'show')->middleware('permission:guard.list,cerrajero');
        Route::post('/guard', 'save')->middleware('permission:guard.create,cerrajero');
        Route::put('/guard/{guard}', 'update')->middleware('permission:guard.edit,cerrajero');
    });

    Route::prefix('hr-management')->group(function () {
        Route::get('/professors', [\App\Http\Controllers\ProfessorController::class, 'index'])->middleware('permission:professor.list,hrManagement');
        Route::post('/professors', [\App\Http\Controllers\ProfessorController::class, 'store'])->middleware('permission:professor.create,hrManagement');
        Route::post('/professors-image/{professor}', [\App\Http\Controllers\ProfessorController::class, 'updateImage'])->middleware('permission:professor.edit,hrManagement');
        Route::put('/professors/{professor}',[\App\Http\Controllers\ProfessorController::class, 'update'])->middleware('permission:professor.edit,hrManagement');
        Route::post('/professors-link',[\App\Http\Controllers\LinksController::class, 'store'])->middleware('permission:professor.edit,hrManagement');
        Route::put('/professors-link/{link}',[\App\Http\Controllers\LinksController::class, 'update'])->middleware('permission:professor.edit,hrManagement');
        Route::delete('/professors-link/{link}',[\App\Http\Controllers\LinksController::class, 'destroy'])->middleware('permission:professor.edit,hrManagement');
        Route::get('/skills', [\App\Http\Controllers\SkillController::class, 'index'])->middleware('permission:skill.list,hrManagement');
        Route::post('/skills', [\App\Http\Controllers\SkillController::class, 'store'])->middleware('permission:skill.create,hrManagement');
        Route::put('/skills/{skill}', [\App\Http\Controllers\SkillController::class, 'update'])->middleware('permission:skill.edit,hrManagement');
        Route::delete('/skills/{skill}', [\App\Http\Controllers\SkillController::class, 'destroy'])->middleware('permission:skill.delete,hrManagement');

        Route::get('/tags', [\App\Http\Controllers\TagController::class, 'index'])->middleware('permission:tag.list,hrManagement');
        Route::post('/tags', [\App\Http\Controllers\TagController::class, 'store'])->middleware('permission:tag.create,hrManagement');
        Route::put('/tags/{tag}', [\App\Http\Controllers\TagController::class, 'update'])->middleware('permission:tag.edit,hrManagement');
        Route::delete('/tags/{tag}', [\App\Http\Controllers\TagController::class, 'destroy'])->middleware('permission:tag.delete,hrManagement');

        Route::get('/students', [\App\Http\Controllers\StudentController::class, 'index'])->middleware('permission:student.list,hrManagement');
        Route::post('/students', [\App\Http\Controllers\StudentController::class, 'store'])->middleware('permission:student.create,hrManagement');
        Route::post('/students-image/{student}', [\App\Http\Controllers\StudentController::class, 'updateImage'])->middleware('permission:student.edit,hrManagement');
        Route::put('/students/{student}',[\App\Http\Controllers\StudentController::class, 'update'])->middleware('permission:student.edit,hrManagement');
        Route::post('/students-link',[\App\Http\Controllers\LinksController::class, 'store'])->middleware('permission:student.edit,hrManagement');
        Route::put('/students-link/{link}',[\App\Http\Controllers\LinksController::class, 'update'])->middleware('permission:student.edit,hrManagement');
        Route::delete('/students-link/{link}',[\App\Http\Controllers\LinksController::class, 'destroy'])->middleware('permission:student.edit,hrManagement');

        Route::get('/contrated-plan', [\App\Http\Controllers\ContratedPlanController::class, 'list'])->middleware('permission:contratedPlan.list,hrManagement');
        Route::post('/contrated-plan', [\App\Http\Controllers\ContratedPlanController::class, 'create'])->middleware('permission:contratedPlan.create,hrManagement');
        Route::put('/contrated-plan/{cplan}', [\App\Http\Controllers\ContratedPlanController::class, 'update'])->middleware('permission:contratedPlan.edit,hrManagement');
        Route::post('/students-contrated-plan/{cplan}', [\App\Http\Controllers\ContratedPlanController::class, 'addStudents'])->middleware('permission:contratedPlan.edit,hrManagement');
        Route::post('/tags-contrated-plan/{cplan}', [\App\Http\Controllers\ContratedPlanController::class, 'addTags'])->middleware('permission:contratedPlan.edit,hrManagement');
        Route::get('/substitute-plan', [\App\Http\Controllers\SubstitutePlanController::class, 'list'])->middleware('permission:contratedPlan.list,hrManagement');
        Route::post('/substitute-plan', [\App\Http\Controllers\SubstitutePlanController::class, 'create'])->middleware('permission:contratedPlan.create,hrManagement');
        Route::put('/substitute-plan/{cplan}', [\App\Http\Controllers\SubstitutePlanController::class, 'update'])->middleware('permission:contratedPlan.edit,hrManagement');

        Route::post('/massive-class-creation', [\App\Http\Controllers\ImpartedClassController::class, 'massiveClassCreation'])->middleware('permission:contratedPlan.edit,hrManagement');
        Route::put('/class-edit/{ic}', [\App\Http\Controllers\ImpartedClassController::class, 'update'])->middleware('permission:contratedPlan.edit,hrManagement');

        Route::get('/diagnostic-class', [\App\Http\Controllers\DiagnosticClassController::class, 'index'])->middleware('permission:contratedPlan.list,hrManagement');
        Route::post('/diagnostic-class', [\App\Http\Controllers\DiagnosticClassController::class, 'store'])->middleware('permission:contratedPlan.create,hrManagement');
        Route::put('/diagnostic-class/{diagClass}', [\App\Http\Controllers\DiagnosticClassController::class, 'update'])->middleware('permission:contratedPlan.edit,hrManagement');
        Route::delete('/diagnostic-class/{diagClass}', [\App\Http\Controllers\DiagnosticClassController::class, 'destroy'])->middleware('permission:contratedPlan.edit,hrManagement');

        Route::post('/invoice-update/{pinvoice}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'update'])->middleware('permission:invoices.edit,hrManagement');
        Route::get('/invoice-preview/{professor}/{start_date}/{end_date}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'previewClasses'])->middleware('permission:invoices.list,hrManagement');
        Route::get('/invoice-list', [\App\Http\Controllers\ProfessorInvoiceController::class, 'listAll'])->middleware('permission:invoices.edit,hrManagement');
        Route::get('/invoice/{pinvoice}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'generatePDF'])->middleware('permission:invoices.list,hrManagement');
    });

    Route::prefix('professor-app')->group(function () {
        Route::post('/professors-image/{professor}', [\App\Http\Controllers\ProfessorController::class, 'updateImage'])->middleware('permission:professor-rol.edit,professorApp');
        Route::put('/professors/{professor}',[\App\Http\Controllers\ProfessorController::class, 'update'])->middleware('permission:professor-rol.edit,professorApp');
        Route::post('/professors-link',[\App\Http\Controllers\LinksController::class, 'store'])->middleware('permission:professor-rol.edit,professorApp');
        Route::put('/professors-link/{link}',[\App\Http\Controllers\LinksController::class, 'update'])->middleware('permission:professor-rol.edit,professorApp');
        Route::delete('/professors-link/{link}',[\App\Http\Controllers\LinksController::class, 'destroy'])->middleware('permission:professor-rol.edit,professorApp');
        Route::get('/skills', [\App\Http\Controllers\SkillController::class, 'index'])->middleware('permission:professor-rol.edit,professorApp');

        Route::post('/class-start', [\App\Http\Controllers\ImpartedClassController::class, 'store'])->middleware('permission:professor-cls.start,professorApp');
        Route::put('/class-edit/{ic}', [\App\Http\Controllers\ImpartedClassController::class, 'update'])->middleware('permission:professor-cls.edit,professorApp');
        Route::put('/class-link/{ic}', [\App\Http\Controllers\ImpartedClassController::class, 'addLink'])->middleware('permission:professor-cls.edit,professorApp');

        Route::get('/contrated-plan/{professor}', [\App\Http\Controllers\ContratedPlanController::class, 'filteredList'])->middleware('permission:professor-plans.list,professorApp');
        Route::get('/substitute-plan/{professor}', [\App\Http\Controllers\SubstitutePlanController::class, 'filteredList'])->middleware('permission:professor-plans.list,professorApp');

        Route::put('/diagnostic-class/{diagClass}', [\App\Http\Controllers\DiagnosticClassController::class, 'update'])->middleware('permission:professor-plans.list,professorApp');
        Route::get('/diagnostic-class/{professor}', [\App\Http\Controllers\DiagnosticClassController::class, 'indexProfessor'])->middleware('permission:professor-plans.list,professorApp');

        Route::post('/invoice-create', [\App\Http\Controllers\ProfessorInvoiceController::class, 'store']);
        Route::delete('/invoice-delete/{pinvoice}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'delete']);
        Route::post('/invoice-update/{pinvoice}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'update']);
        Route::get('/invoice-preview/{professor}/{start_date}/{end_date}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'previewClasses']);
        Route::get('/invoices-professor/{professor}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'listByProfessor']);
        Route::get('/invoice/{pinvoice}', [\App\Http\Controllers\ProfessorInvoiceController::class, 'generatePDF']);
    });

    Route::prefix('student-app')->group(function () {
        Route::post('/students-image/{student}', [\App\Http\Controllers\StudentController::class, 'updateImage'])->middleware('permission:student-rol.edit,studentApp');
        Route::put('/students/{student}',[\App\Http\Controllers\StudentController::class, 'update'])->middleware('permission:student-rol.edit,studentApp');

        Route::post('/class-start/{ic}', [\App\Http\Controllers\ImpartedClassController::class, 'syncStudent'])->middleware('permission:student-cls.start,studentApp');

        Route::get('/contrated-plan/{student}', [\App\Http\Controllers\ContratedPlanController::class, 'filteredByStudentList'])->middleware('permission:student-plans.list,studentApp');
    });
});
