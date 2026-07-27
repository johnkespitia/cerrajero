<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\GoogleCalendarConfig;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleCalendarService
{
    protected $client;
    protected $calendarId;
    protected $config;

    public function __construct()
    {
        $this->config = GoogleCalendarConfig::getActive();
        
        if (!$this->config || !$this->config->active) {
            // Intentar usar variables de entorno como fallback
            $envCalendarId = env('GOOGLE_CALENDAR_ID');
            if ($envCalendarId) {
                $this->calendarId = $envCalendarId;
                $this->initializeClientFromEnv();
            } else {
                Log::info('Google Calendar no está configurado o está inactivo');
            }
            return;
        }

        $this->initializeClient();
        $this->calendarId = $this->config->calendar_id;
    }

    /**
     * Inicializar cliente desde variables de entorno (fallback)
     */
    protected function initializeClientFromEnv()
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Campo Verde Reservations');
        $this->client->setScopes(Google_Service_Calendar::CALENDAR);

        $credentials = env('GOOGLE_CALENDAR_CREDENTIALS');
        if ($credentials) {
            $credentialsArray = json_decode($credentials, true);
            $this->client->setAuthConfig($credentialsArray);
            
            // Si es Service Account, no necesita access_type ni prompt
            if (isset($credentialsArray['type']) && $credentialsArray['type'] === 'service_account') {
                // Service Account se autentica automáticamente
                Log::info('Usando Service Account para Google Calendar');
            } else {
                // OAuth 2.0
                $this->client->setAccessType('offline');
            }
        }
    }

    /**
     * Inicializar cliente de Google
     */
    protected function initializeClient()
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Campo Verde Reservations');
        $this->client->setScopes(Google_Service_Calendar::CALENDAR);

        // Configurar credenciales
        $credentials = $this->config->getCredentialsArray();
        if ($credentials) {
            $this->client->setAuthConfig($credentials);
            
            // Detectar si es Service Account o OAuth 2.0
            $isServiceAccount = isset($credentials['type']) && $credentials['type'] === 'service_account';
            
            if ($isServiceAccount) {
                // Service Account: no necesita tokens ni refresh
                Log::info('Usando Service Account para Google Calendar');
            } else {
                // OAuth 2.0: configurar access type y prompt
                $this->client->setAccessType('offline');
                $this->client->setPrompt('select_account consent');
                
                // Configurar tokens si existen (solo para OAuth 2.0)
                if ($this->config->access_token) {
                    $this->client->setAccessToken([
                        'access_token' => $this->config->access_token,
                        'refresh_token' => $this->config->refresh_token,
                        'expires_in' => $this->config->token_expires_at 
                            ? $this->config->token_expires_at->diffInSeconds(now())
                            : 3600,
                        'created' => $this->config->token_expires_at 
                            ? $this->config->token_expires_at->subSeconds(3600)->timestamp
                            : now()->timestamp,
                    ]);

                    // Refrescar token si está expirado (solo para OAuth 2.0)
                    if (!$isServiceAccount && $this->config->isTokenExpired() && $this->config->refresh_token) {
                        $this->refreshAccessToken();
                    }
                }
            }
        } else {
            // Fallback a variables de entorno si no hay configuración en BD
            $envCredentials = env('GOOGLE_CALENDAR_CREDENTIALS');
            if ($envCredentials) {
                $credentialsArray = json_decode($envCredentials, true);
                $this->client->setAuthConfig($credentialsArray);
                
                // Si es Service Account, no necesita access_type ni prompt
                if (isset($credentialsArray['type']) && $credentialsArray['type'] === 'service_account') {
                    Log::info('Usando Service Account para Google Calendar (desde env)');
                } else {
                    $this->client->setAccessType('offline');
                }
            }
        }
    }

    /**
     * Verificar si es Service Account
     */
    protected function isServiceAccount()
    {
        if (!$this->config) {
            return false;
        }
        
        $credentials = $this->config->getCredentialsArray();
        return $credentials && isset($credentials['type']) && $credentials['type'] === 'service_account';
    }

    /**
     * Refrescar token de acceso (solo para OAuth 2.0, no Service Account)
     */
    protected function refreshAccessToken()
    {
        // Service Accounts no necesitan refresh tokens
        if ($this->isServiceAccount()) {
            return;
        }

        try {
            if (!$this->config->refresh_token) {
                throw new \Exception('No hay refresh token disponible');
            }

            if ($this->client->isAccessTokenExpired()) {
                $this->client->refreshToken($this->config->refresh_token);
                $accessToken = $this->client->getAccessToken();

                if ($accessToken) {
                    $this->config->update([
                        'access_token' => $accessToken['access_token'],
                        'token_expires_at' => Carbon::now()->addSeconds($accessToken['expires_in']),
                    ]);

                    if (isset($accessToken['refresh_token'])) {
                        $this->config->update(['refresh_token' => $accessToken['refresh_token']]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error refreshing Google Calendar access token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar si el servicio está configurado y activo
     */
    public function isConfigured()
    {
        return $this->config && $this->config->active && $this->calendarId;
    }

    public function createEvent(Reservation $reservation)
    {
        if (!$this->isConfigured()) {
            Log::info('Google Calendar no configurado, omitiendo creación de evento');
            return null;
        }

        try {
            $reservation = $this->prepareReservationForCalendar($reservation);

            // Refrescar token si es necesario (solo para OAuth 2.0)
            if (!$this->isServiceAccount() && $this->config->isTokenExpired()) {
                $this->refreshAccessToken();
            }

            $service = new Google_Service_Calendar($this->client);

            $typeLabel = $this->isDayPass($reservation) ? 'Pasadía' : 'Habitación';
            $event = new Google_Service_Calendar_Event([
                'summary' => "Reserva #{$reservation->reservation_number} ({$typeLabel}) - {$reservation->customer->display_name}",
                'description' => $this->buildEventDescription($reservation),
                'start' => new Google_Service_Calendar_EventDateTime([
                    'dateTime' => $reservation->check_in_date->format('Y-m-d') . 'T' . ($reservation->check_in_time 
                        ? Carbon::parse($reservation->check_in_time)->format('H:i:s')
                        : '00:00:00'),
                    'timeZone' => 'America/Bogota',
                ]),
                'end' => new Google_Service_Calendar_EventDateTime([
                    'dateTime' => ($reservation->check_out_date 
                        ? $reservation->check_out_date->format('Y-m-d')
                        : $reservation->check_in_date->format('Y-m-d')) . 'T' . ($reservation->check_out_time
                        ? Carbon::parse($reservation->check_out_time)->format('H:i:s')
                        : '23:59:59'),
                    'timeZone' => 'America/Bogota',
                ]),
            ]);

            $createdEvent = $service->events->insert($this->calendarId, $event);

            $reservation->update([
                'google_calendar_event_id' => $createdEvent->getId(),
                'google_calendar_link' => $createdEvent->getHtmlLink()
            ]);

            return $createdEvent;
        } catch (\Exception $e) {
            Log::error('Error creating Google Calendar event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateEvent(Reservation $reservation)
    {
        if (!$this->isConfigured()) {
            Log::info('Google Calendar no configurado, omitiendo actualización de evento');
            return null;
        }

        if (!$reservation->google_calendar_event_id) {
            return $this->createEvent($reservation);
        }

        try {
            $reservation = $this->prepareReservationForCalendar($reservation);

            // Refrescar token si es necesario (solo para OAuth 2.0)
            if (!$this->isServiceAccount() && $this->config->isTokenExpired()) {
                $this->refreshAccessToken();
            }

            $service = new Google_Service_Calendar($this->client);
            $event = $service->events->get($this->calendarId, $reservation->google_calendar_event_id);

            $typeLabel = $this->isDayPass($reservation) ? 'Pasadía' : 'Habitación';
            $event->setSummary("Reserva #{$reservation->reservation_number} ({$typeLabel}) - {$reservation->customer->display_name}");
            $event->setDescription($this->buildEventDescription($reservation));
            
            $startDateTime = $reservation->check_in_date->format('Y-m-d') . 'T' . ($reservation->check_in_time 
                ? Carbon::parse($reservation->check_in_time)->format('H:i:s')
                : '00:00:00');
            $event->getStart()->setDateTime($startDateTime);
            $event->getStart()->setTimeZone('America/Bogota');

            $endDate = $reservation->check_out_date ?? $reservation->check_in_date;
            $endDateTime = $endDate->format('Y-m-d') . 'T' . ($reservation->check_out_time
                ? Carbon::parse($reservation->check_out_time)->format('H:i:s')
                : '23:59:59');
            $event->getEnd()->setDateTime($endDateTime);
            $event->getEnd()->setTimeZone('America/Bogota');

            $updatedEvent = $service->events->update(
                $this->calendarId,
                $reservation->google_calendar_event_id,
                $event
            );

            $reservation->update([
                'google_calendar_link' => $updatedEvent->getHtmlLink()
            ]);

            return $updatedEvent;
        } catch (\Exception $e) {
            Log::error('Error updating Google Calendar event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteEvent(Reservation $reservation)
    {
        if (!$this->isConfigured() || !$reservation->google_calendar_event_id) {
            return;
        }

        try {
            // Refrescar token si es necesario (solo para OAuth 2.0)
            if (!$this->isServiceAccount() && $this->config->isTokenExpired()) {
                $this->refreshAccessToken();
            }

            $service = new Google_Service_Calendar($this->client);
            $service->events->delete($this->calendarId, $reservation->google_calendar_event_id);

            $reservation->update([
                'google_calendar_event_id' => null,
                'google_calendar_link' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting Google Calendar event: ' . $e->getMessage());
        }
    }

    /**
     * Sincroniza una reserva con Google Calendar (crea, actualiza o elimina según el estado).
     */
    public function syncReservation(Reservation $reservation): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $reservation = $reservation->fresh();
        if (!$reservation) {
            return;
        }

        try {
            if ($reservation->status === 'cancelled') {
                $this->deleteEvent($reservation);
                return;
            }

            $this->updateEvent($reservation);
        } catch (\Exception $e) {
            Log::error('Error syncing reservation to Google Calendar: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
            ]);
        }
    }

    protected function prepareReservationForCalendar(Reservation $reservation): Reservation
    {
        return $reservation->loadMissing([
            'customer',
            'room',
            'roomType',
            'guests',
            'additionalServices.additionalService',
            'payments.paymentType',
            'childReservations.room',
        ]);
    }

    protected function buildEventDescription(Reservation $reservation)
    {
        $reservation = $this->prepareReservationForCalendar($reservation);
        $isDayPass = $this->isDayPass($reservation);

        $description = "Reserva #{$reservation->reservation_number}\n";
        $description .= "Tipo: " . ($isDayPass ? 'Pasadía' : 'Habitación') . "\n";
        $description .= "Estado: {$this->formatReservationStatus($reservation->status)}\n";
        $description .= "Estado de pago: {$this->formatPaymentStatus($reservation->payment_status)}\n\n";

        $customer = $reservation->customer;
        if ($customer->customer_type === 'company') {
            $description .= "Cliente (Empresa): {$customer->company_name}\n";
            $description .= "NIT: {$customer->company_nit}\n";
            if ($customer->company_legal_representative) {
                $description .= "Representante Legal: {$customer->company_legal_representative}\n";
            }
        } else {
            $description .= "Cliente: {$customer->display_name}\n";
        }
        $description .= "Email: {$customer->email}\n";
        $description .= "Teléfono: {$customer->phone_number}\n\n";

        if ($isDayPass) {
            $description .= "--- PASADÍA ---\n";
            $description .= "Fecha: {$reservation->check_in_date->format('Y-m-d')}";
            if ($reservation->check_in_time) {
                $description .= ' ' . Carbon::parse($reservation->check_in_time)->format('H:i');
            }
            $description .= "\n";
            $description .= "Personas: {$reservation->adults} adultos, {$reservation->children} niños, {$reservation->infants} bebés";
        } else {
            $description .= "--- HABITACIÓN ---\n";
            if ($reservation->room) {
                $description .= "Habitación: {$reservation->room->display_name}\n";
            }
            if ($reservation->roomType) {
                $description .= "Tipo de habitación: {$reservation->roomType->name}\n";
            }

            $description .= "Check-in: {$reservation->check_in_date->format('Y-m-d')}";
            if ($reservation->check_in_time) {
                $description .= ' ' . Carbon::parse($reservation->check_in_time)->format('H:i');
            }
            $description .= "\n";

            $checkOutDate = $reservation->check_out_date ?? $reservation->check_in_date;
            $description .= "Check-out: {$checkOutDate->format('Y-m-d')}";
            if ($reservation->check_out_time) {
                $description .= ' ' . Carbon::parse($reservation->check_out_time)->format('H:i');
            }
            $description .= "\n";

            $description .= "Huéspedes: {$reservation->adults} adultos, {$reservation->children} niños, {$reservation->infants} bebés";
        }

        if ((int) $reservation->courtesy_guests > 0) {
            $description .= ", {$reservation->courtesy_guests} cortesía";
        }
        $description .= "\n";

        if ($reservation->guests && $reservation->guests->count() > 0) {
            $guestLabel = $isDayPass ? 'Personas registradas' : 'Huéspedes registrados';
            $description .= "\n{$guestLabel}:\n";
            foreach ($reservation->guests as $guest) {
                $description .= "- {$guest->full_name}";
                if ($guest->document_number) {
                    $description .= " ({$guest->document_type}: {$guest->document_number})";
                }
                if ($guest->is_primary_guest) {
                    $description .= " [Principal]";
                }
                $description .= "\n";
            }
        }

        $additionalServices = $reservation->additionalServices ?? collect();
        $foodServices = $additionalServices->filter(function ($row) {
            return $row->additionalService && $row->additionalService->is_food_service;
        });

        $description .= "\n--- ALIMENTACIÓN ---\n";
        if ($foodServices->isEmpty()) {
            $description .= "Alimentación: NO\n";
        } else {
            $description .= "Alimentación: SÍ\n";
            foreach ($foodServices as $row) {
                $service = $row->additionalService;
                $mealLabel = $this->formatMealType($service->meal_type);
                $description .= "- {$service->name} ({$mealLabel}): " . $this->formatServiceQuantity($row) . "\n";
            }
        }

        $description .= "\n--- SERVICIOS ADICIONALES ---\n";
        if ($additionalServices->isEmpty()) {
            $description .= "Ninguno\n";
        } else {
            foreach ($additionalServices as $row) {
                $serviceName = $row->additionalService->name ?? 'Servicio';
                $tag = ($row->additionalService && $row->additionalService->is_food_service) ? ' [Alimentación]' : '';
                $description .= "- {$serviceName}{$tag}: " . $this->formatServiceQuantity($row) . "\n";
            }
        }

        $finalPrice = (float) ($reservation->final_price ?? $reservation->total_price ?? 0);
        $totalPaid = (float) ($reservation->payments ? $reservation->payments->sum('amount') : 0);

        $description .= "\n--- FINANCIERO ---\n";
        $description .= "Total: $" . number_format($finalPrice, 0, '.', ',') . "\n";
        $description .= "Pagado: $" . number_format($totalPaid, 0, '.', ',') . "\n";

        if ($reservation->payment_status === 'free' && $reservation->free_reservation_reason) {
            $description .= "Reserva gratis: {$reservation->free_reservation_reason}\n";
        }

        if ($reservation->payments && $reservation->payments->count() > 0) {
            $description .= "\nPagos registrados:\n";
            foreach ($reservation->payments as $payment) {
                $paymentType = $payment->paymentType->name ?? 'Sin tipo';
                $concept = $payment->concept ? " — {$payment->concept}" : '';
                $reference = $payment->payment_reference ? " (Ref: {$payment->payment_reference})" : '';
                $date = $payment->created_at ? $payment->created_at->format('Y-m-d') : '';
                $description .= "- $" . number_format((float) $payment->amount, 0, '.', ',') . " — {$paymentType}{$concept}{$reference}";
                if ($date) {
                    $description .= " — {$date}";
                }
                $description .= "\n";
            }
        }

        if (
            !$isDayPass
            && $reservation->is_group_reservation
            && $reservation->childReservations
            && $reservation->childReservations->count() > 0
        ) {
            $description .= "\n--- RESERVA DE GRUPO ---\n";
            $description .= "Habitaciones adicionales:\n";
            foreach ($reservation->childReservations as $child) {
                $roomName = $child->room->display_name ?? "Habitación #{$child->room_id}";
                $description .= "- {$roomName} (seq. {$child->room_sequence})\n";
            }
        }

        if ($reservation->special_requests) {
            $description .= "\nSolicitudes especiales: {$reservation->special_requests}\n";
        }

        return $description;
    }

    protected function isDayPass(Reservation $reservation): bool
    {
        return $reservation->reservation_type === 'day_pass';
    }

    protected function formatServiceQuantity($row): string
    {
        $quantity = $row->quantity ?? 1;
        $days = $row->service_days ?? null;

        if ($days && (float) $days !== (float) $quantity) {
            return "{$quantity} ítems, {$days} días";
        }

        if ($days) {
            return "{$quantity} ({$days} días)";
        }

        return (string) $quantity;
    }

    protected function formatReservationStatus(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'checked_in' => 'Check-in',
            'checked_out' => 'Check-out',
            'cancelled' => 'Cancelada',
            default => $status ?? 'N/A',
        };
    }

    protected function formatPaymentStatus(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'paid' => 'Pagado',
            'free' => 'Gratis',
            'refunded' => 'Reembolsado',
            default => $status ?? 'N/A',
        };
    }

    protected function formatMealType(?string $mealType): string
    {
        return match ($mealType) {
            'breakfast' => 'Desayuno',
            'lunch' => 'Almuerzo',
            'dinner' => 'Cena',
            'refreshment' => 'Refrigerio',
            null, '' => 'Alimentación completa',
            default => $mealType,
        };
    }

    /**
     * Obtener URL de autorización para OAuth (solo para OAuth 2.0, no Service Account)
     */
    public function getAuthUrl($config = null)
    {
        $configToUse = $config ?? $this->config;
        
        if (!$configToUse) {
            throw new \Exception('Google Calendar no está configurado');
        }

        $credentials = $configToUse->getCredentialsArray();
        if (!$credentials) {
            throw new \Exception('Credenciales no configuradas');
        }

        // Verificar si es Service Account
        $isServiceAccount = isset($credentials['type']) && $credentials['type'] === 'service_account';
        if ($isServiceAccount) {
            throw new \Exception('Las Service Accounts no requieren autorización OAuth. Solo necesitas compartir el calendario con el email de la Service Account.');
        }

        // Crear cliente temporal con las credenciales (OAuth 2.0)
        $client = new Google_Client();
        $client->setApplicationName('Campo Verde Reservations');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setAuthConfig($credentials);

        // Obtener redirect URI desde configuración o usar el por defecto
        $redirectUri = env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL') . '/api/google-calendar/callback');
        $client->setRedirectUri($redirectUri);
        
        Log::info('Generando URL de autorización con redirect URI: ' . $redirectUri);
        
        return $client->createAuthUrl();
    }

    /**
     * Intercambiar código de autorización por tokens
     */
    public function handleCallback($code, $config = null)
    {
        $configToUse = $config ?? $this->config;
        
        if (!$configToUse) {
            throw new \Exception('Google Calendar no está configurado');
        }

        try {
            // Crear cliente temporal
            $client = new Google_Client();
            $client->setApplicationName('Campo Verde Reservations');
            $client->setScopes(Google_Service_Calendar::CALENDAR);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            $credentials = $configToUse->getCredentialsArray();
            if ($credentials) {
                $client->setAuthConfig($credentials);
            } else {
                throw new \Exception('Credenciales no configuradas');
            }

            // Obtener redirect URI desde configuración o usar el por defecto
            $redirectUri = env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL') . '/api/google-calendar/callback');
            $client->setRedirectUri($redirectUri);
            
            Log::info('Intercambiando código de autorización con redirect URI: ' . $redirectUri);
            
            $accessToken = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                throw new \Exception('Error obteniendo token: ' . $accessToken['error_description']);
            }

            $configToUse->update([
                'access_token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'] ?? $configToUse->refresh_token,
                'token_expires_at' => Carbon::now()->addSeconds($accessToken['expires_in']),
            ]);

            return $accessToken;
        } catch (\Exception $e) {
            Log::error('Error handling Google Calendar callback: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Probar conexión con Google Calendar
     */
    public function testConnection()
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Google Calendar no está configurado o está inactivo'
            ];
        }

        try {
            // Refrescar token si es necesario (solo para OAuth 2.0)
            if (!$this->isServiceAccount() && $this->config->isTokenExpired()) {
                $this->refreshAccessToken();
            }

            $service = new Google_Service_Calendar($this->client);
            $calendar = $service->calendars->get($this->calendarId);

            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'calendar' => [
                    'id' => $calendar->getId(),
                    'summary' => $calendar->getSummary(),
                    'timezone' => $calendar->getTimeZone(),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error testing Google Calendar connection: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener eventos del calendario en un rango de fechas
     */
    public function getEvents($timeMin = null, $timeMax = null, $maxResults = 2500)
    {
        if (!$this->isConfigured()) {
            Log::warning('Google Calendar no está configurado para obtener eventos');
            return [];
        }

        // Asegurar que el cliente esté inicializado
        if (!$this->client) {
            Log::warning('Cliente de Google Calendar no inicializado, intentando inicializar...');
            $this->initializeClient();
            if (!$this->client) {
                Log::error('No se pudo inicializar el cliente de Google Calendar');
                return [];
            }
        }

        try {
            // Refrescar token si es necesario (solo para OAuth 2.0)
            if (!$this->isServiceAccount() && $this->config->isTokenExpired()) {
                $this->refreshAccessToken();
            }

            $service = new Google_Service_Calendar($this->client);
            
            // Si no se especifican fechas, usar el mes actual
            if (!$timeMin) {
                $timeMin = Carbon::now()->startOfMonth()->toRfc3339String();
            }
            if (!$timeMax) {
                $timeMax = Carbon::now()->endOfMonth()->toRfc3339String();
            }

            $optParams = [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'maxResults' => $maxResults,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ];

            Log::info('Obteniendo eventos de Google Calendar', [
                'calendar_id' => $this->calendarId,
                'timeMin' => $timeMin,
                'timeMax' => $timeMax
            ]);

            $events = $service->events->listEvents($this->calendarId, $optParams);
            
            Log::info('Eventos obtenidos de Google Calendar', [
                'total_items' => count($events->getItems()),
                'next_page_token' => $events->getNextPageToken()
            ]);
            
            $formattedEvents = [];
            foreach ($events->getItems() as $event) {
                $start = $event->getStart();
                $end = $event->getEnd();
                
                // Intentar encontrar la reserva relacionada por google_calendar_event_id
                $reservation = \App\Models\Reservation::where('google_calendar_event_id', $event->getId())->first();
                
                $formattedEvents[] = [
                    'id' => $event->getId(),
                    'title' => $event->getSummary() ?? 'Sin título',
                    'description' => $event->getDescription() ?? '',
                    'start' => $start->getDateTime() ?? $start->getDate(),
                    'end' => $end->getDateTime() ?? $end->getDate(),
                    'allDay' => $start->getDateTime() === null,
                    'htmlLink' => $event->getHtmlLink(),
                    'location' => $event->getLocation() ?? '',
                    'status' => $event->getStatus(),
                    'reservation_id' => $reservation ? $reservation->id : null,
                    'reservation_number' => $reservation ? $reservation->reservation_number : null,
                ];
            }

            return $formattedEvents;
        } catch (\Exception $e) {
            Log::error('Error getting Google Calendar events: ' . $e->getMessage());
            throw $e;
        }
    }
}



