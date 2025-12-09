<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 404);
            }
        });
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 400);
            }
        });
        $this->renderable(function (\Exception $e, $request) {
            // Detectar error de PDO no encontrado
            if (strpos($e->getMessage(), "Class 'PDO' not found") !== false || 
                strpos($e->getMessage(), 'Class "PDO" not found') !== false) {
                $message = "La extensión PDO de PHP no está habilitada en el servidor. ";
                $message .= "Por favor, contacta al administrador del servidor para habilitar las extensiones 'pdo' y 'pdo_mysql'. ";
                $message .= "Puedes verificar el estado de PDO accediendo a: " . url('/check_pdo.php');
                
                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => $message,
                        'error' => 'PDO_NOT_FOUND',
                        'help' => 'Verifica las extensiones PHP en tu servidor. Accede a /check_pdo.php para más información.'
                    ], 500);
                }
            }
            
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 500);
            }
        });
    }
}
