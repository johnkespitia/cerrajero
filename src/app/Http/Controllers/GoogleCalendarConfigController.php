<?php

namespace App\Http\Controllers;

use App\Models\GoogleCalendarConfig;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleCalendarConfigController extends Controller
{
    protected $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Obtener configuración actual
     */
    public function index()
    {
        $config = GoogleCalendarConfig::getActive() ?? GoogleCalendarConfig::first();

        if (!$config) {
            return response()->json([
                'config' => null,
                'is_configured' => false
            ]);
        }

        // Detectar tipo de credenciales
        $credentials = $config->getCredentialsArray();
        $isServiceAccount = $credentials && isset($credentials['type']) && $credentials['type'] === 'service_account';
        
        // No exponer tokens completos por seguridad
        $configData = [
            'id' => $config->id,
            'calendar_id' => $config->calendar_id,
            'active' => $config->active,
            'has_credentials' => !empty($config->credentials_json),
            'is_service_account' => $isServiceAccount,
            'service_account_email' => $isServiceAccount ? ($credentials['client_email'] ?? null) : null,
            'has_access_token' => !empty($config->access_token),
            'has_refresh_token' => !empty($config->refresh_token),
            'token_expires_at' => $config->token_expires_at,
            'is_token_expired' => $config->isTokenExpired(),
            'created_at' => $config->created_at,
            'updated_at' => $config->updated_at,
        ];

        return response()->json([
            'config' => $configData,
            'is_configured' => $config->active && !empty($config->calendar_id)
        ]);
    }

    /**
     * Crear o actualizar configuración
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'calendar_id' => 'required|string|max:255',
            'credentials_json' => 'required|string',
            'active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Validar que el JSON de credenciales sea válido
        $credentials = json_decode($request->credentials_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'El JSON de credenciales no es válido',
                'error' => json_last_error_msg()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Desactivar otras configuraciones si se marca como activa
            if ($request->boolean('active', true)) {
                GoogleCalendarConfig::where('id', '!=', $request->id ?? 0)
                    ->update(['active' => false]);
            }

            $config = GoogleCalendarConfig::updateOrCreate(
                ['id' => $request->id ?? null],
                [
                    'calendar_id' => $request->calendar_id,
                    'credentials_json' => $request->credentials_json,
                    'active' => $request->boolean('active', true),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Configuración de Google Calendar guardada exitosamente',
                'config' => [
                    'id' => $config->id,
                    'calendar_id' => $config->calendar_id,
                    'active' => $config->active,
                    'has_credentials' => true,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving Google Calendar config: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al guardar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener URL de autorización OAuth (solo para OAuth 2.0)
     */
    public function getAuthUrl()
    {
        try {
            $config = GoogleCalendarConfig::getActive() ?? GoogleCalendarConfig::first();
            
            if (!$config) {
                return response()->json([
                    'message' => 'Primero debe configurar las credenciales de Google Calendar'
                ], 400);
            }

            $credentials = $config->getCredentialsArray();
            $isServiceAccount = $credentials && isset($credentials['type']) && $credentials['type'] === 'service_account';
            
            if ($isServiceAccount) {
                return response()->json([
                    'message' => 'Las Service Accounts no requieren autorización OAuth',
                    'service_account_email' => $credentials['client_email'] ?? null,
                    'instructions' => 'Solo necesitas compartir tu calendario con el email de la Service Account: ' . ($credentials['client_email'] ?? 'N/A')
                ], 400);
            }

            $authUrl = $this->calendarService->getAuthUrl($config);
            $redirectUri = env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL') . '/api/google-calendar/callback');
            
            return response()->json([
                'auth_url' => $authUrl,
                'redirect_uri' => $redirectUri
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting Google Calendar auth URL: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener URL de autorización',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener redirect URI configurado
     */
    public function getRedirectUri()
    {
        $redirectUri = env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL') . '/api/google-calendar/callback');
        
        return response()->json([
            'redirect_uri' => $redirectUri,
            'instructions' => 'Este es el Redirect URI que debes agregar en Google Cloud Console en la configuración de tu credencial OAuth 2.0'
        ]);
    }

    /**
     * Manejar callback de OAuth (público, sin autenticación)
     */
    public function handleCallback(Request $request)
    {
        try {
            $code = $request->get('code');
            $error = $request->get('error');

            if ($error) {
                Log::error('Google Calendar OAuth error: ' . $error);
                return view('google_calendar.callback_error', [
                    'error' => $error,
                    'error_description' => $request->get('error_description', 'Error desconocido')
                ]);
            }

            if (!$code) {
                return view('google_calendar.callback_error', [
                    'error' => 'missing_code',
                    'error_description' => 'No se recibió el código de autorización'
                ]);
            }

            $config = GoogleCalendarConfig::getActive() ?? GoogleCalendarConfig::first();
            if (!$config) {
                return view('google_calendar.callback_error', [
                    'error' => 'no_config',
                    'error_description' => 'No hay configuración de Google Calendar'
                ]);
            }

            $this->calendarService->handleCallback($code, $config);

            return view('google_calendar.callback_success', [
                'message' => 'Autorización exitosa. Google Calendar está configurado correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error handling Google Calendar callback: ' . $e->getMessage());
            return view('google_calendar.callback_error', [
                'error' => 'exception',
                'error_description' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener eventos del calendario
     */
    public function getEvents(Request $request)
    {
        try {
            $timeMin = $request->get('timeMin');
            $timeMax = $request->get('timeMax');
            $maxResults = $request->get('maxResults', 2500);

            Log::info('Solicitud de eventos recibida', [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'maxResults' => $maxResults
            ]);

            $events = $this->calendarService->getEvents($timeMin, $timeMax, $maxResults);

            Log::info('Eventos retornados al frontend', [
                'count' => count($events)
            ]);

            return response()->json([
                'success' => true,
                'events' => $events,
                'count' => count($events)
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting Google Calendar events: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener eventos: ' . $e->getMessage(),
                'events' => []
            ], 500);
        }
    }

    /**
     * Probar conexión con Google Calendar
     */
    public function testConnection()
    {
        try {
            $result = $this->calendarService->testConnection();
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error testing Google Calendar connection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al probar la conexión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar/desactivar sincronización
     */
    public function toggleActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $config = GoogleCalendarConfig::getActive() ?? GoogleCalendarConfig::first();
            
            if (!$config) {
                return response()->json([
                    'message' => 'No hay configuración de Google Calendar'
                ], 404);
            }

            $config->update(['active' => $request->boolean('active')]);

            return response()->json([
                'message' => $request->boolean('active') 
                    ? 'Sincronización con Google Calendar activada'
                    : 'Sincronización con Google Calendar desactivada',
                'config' => [
                    'id' => $config->id,
                    'active' => $config->active,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling Google Calendar active: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al cambiar el estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar configuración
     */
    public function destroy($id)
    {
        try {
            $config = GoogleCalendarConfig::find($id);
            
            if (!$config) {
                return response()->json([
                    'message' => 'Configuración no encontrada'
                ], 404);
            }

            $config->delete();

            return response()->json([
                'message' => 'Configuración eliminada exitosamente'
            ], 204);
        } catch (\Exception $e) {
            Log::error('Error deleting Google Calendar config: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

