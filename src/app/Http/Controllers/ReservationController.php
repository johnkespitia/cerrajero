<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ReservationPayment;
use App\Models\ReservationSetting;
use App\Models\CleaningRecord;
use App\Services\GoogleCalendarService;
use App\Services\ReservationCertificateService;
use App\Services\ReservationEmailService;
use App\Services\ReservationPriceCalculator;
use App\Services\ReservationAuditService;
use App\Services\ReservationValidationService;
use App\Services\ReservationNotificationService;
use App\Services\ReservationCancellationService;
use App\Services\AdditionalServicePriceCalculator;
use App\Models\AdditionalService;
use App\Models\ServicePackage;
use App\Models\ReservationAdditionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReservationController extends Controller
{
    protected $googleCalendarService;
    protected $certificateService;
    protected $emailService;
    protected $priceCalculator;
    protected $auditService;
    protected $validationService;
    protected $notificationService;
    protected $cancellationService;
    protected $additionalServiceCalculator;

    public function __construct(
        GoogleCalendarService $googleCalendarService,
        ReservationCertificateService $certificateService,
        ReservationEmailService $emailService,
        ReservationPriceCalculator $priceCalculator,
        ReservationAuditService $auditService,
        ReservationValidationService $validationService,
        ReservationNotificationService $notificationService,
        ReservationCancellationService $cancellationService,
        AdditionalServicePriceCalculator $additionalServiceCalculator
    ) {
        $this->googleCalendarService = $googleCalendarService;
        $this->certificateService = $certificateService;
        $this->emailService = $emailService;
        $this->priceCalculator = $priceCalculator;
        $this->auditService = $auditService;
        $this->validationService = $validationService;
        $this->notificationService = $notificationService;
        $this->cancellationService = $cancellationService;
        $this->additionalServiceCalculator = $additionalServiceCalculator;
    }

    /**
     * Verificar si un cliente tiene reserva activa (para uso desde módulo de kiosko)
     */
    public function checkActiveReservation(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $activeReservation = Reservation::where('customer_id', $request->customer_id)
            ->where('status', 'checked_in')
            ->with(['customer', 'room', 'roomType'])
            ->first();

        return response()->json([
            'has_active_reservation' => $activeReservation !== null,
            'reservation' => $activeReservation
        ]);
    }

    /**
     * Obtener todas las reservas activas de un cliente (para selector en kiosko)
     */
    public function getActiveReservationsForKiosk(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $activeReservations = Reservation::where('customer_id', $request->customer_id)
            ->where('status', 'checked_in')
            ->with(['customer', 'room', 'roomType'])
            ->orderBy('check_in_date', 'desc')
            ->get()
            ->map(function($reservation) {
                $daysRemaining = 0;
                if ($reservation->check_out_date) {
                    $daysRemaining = max(0, now()->diffInDays($reservation->check_out_date, false));
                }
                
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'room' => $reservation->room ? $reservation->room->number : null,
                    'room_type' => $reservation->roomType ? $reservation->roomType->name : null,
                    'check_in_date' => $reservation->check_in_date->format('Y-m-d'),
                    'check_out_date' => $reservation->check_out_date ? $reservation->check_out_date->format('Y-m-d') : null,
                    'days_remaining' => $daysRemaining,
                    'is_group_reservation' => $reservation->is_group_reservation,
                    'is_main_reservation' => !$reservation->parent_reservation_id,
                    'priority_score' => ($reservation->is_group_reservation && !$reservation->parent_reservation_id ? 1000 : 0) + $daysRemaining
                ];
            })
            ->sortByDesc('priority_score')
            ->values();

        return response()->json([
            'reservations' => $activeReservations,
            'count' => $activeReservations->count()
        ]);
    }

    public function index(Request $request)
    {
        $with = [
            'customer',
            'room',
            'room.roomType',
            'roomType',
            'guests',
            'additionalServices.additionalService',
            'payments',
            'childReservations' => function($query) {
                $query->with(['room', 'room.roomType', 'customer']);
            },
            'parentReservation' => function($query) {
                $query->with(['room', 'room.roomType', 'customer']);
            },
            'cancellationPolicy'
        ];

        // Incluir pagos si se solicita
        if ($request->has('include')) {
            $includeParams = is_array($request->include) 
                ? $request->include 
                : explode(',', $request->include);
            
            if (in_array('payments', $includeParams)) {
                $with[] = 'payments.paymentType';
            }

            // Incluir facturas del kiosko si se solicita
            if (in_array('kiosk_invoices', $includeParams)) {
                $with[] = 'kioskInvoices.payment_type';
                $with[] = 'kioskInvoices.details.kiosk_unit.product';
            }
        }

        $query = Reservation::with($with);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('reservation_type')) {
            $query->where('reservation_type', $request->reservation_type);
        }

        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Filtro por servicio adicional: reservas que tienen ese servicio
        if ($request->has('additional_service_id')) {
            $query->whereHas('additionalServices', function ($q) use ($request) {
                $q->where('additional_service_id', $request->additional_service_id);
            });
        }

        // Filtro por paquete: reservas que tienen al menos uno de los servicios del paquete
        if ($request->has('service_package_id')) {
            $package = \App\Models\ServicePackage::find($request->service_package_id);
            if ($package) {
                $serviceIds = $package->additionalServices->pluck('id')->toArray();
                if (!empty($serviceIds)) {
                    $query->whereHas('additionalServices', function ($q) use ($serviceIds) {
                        $q->whereIn('additional_service_id', $serviceIds);
                    });
                }
            }
        }

        if ($request->has('date_from')) {
            $query->where('check_in_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('check_in_date', '<=', $request->date_to);
        }

        if ($request->boolean('main_reservations_only')) {
            $query->whereNull('parent_reservation_id');
        }

        // Búsqueda por número de reserva
        if ($request->has('reservation_number')) {
            $query->where('reservation_number', 'like', '%' . $request->reservation_number . '%');
        }

        // Búsqueda por ID de cliente
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Búsqueda por nombre de cliente
        if ($request->has('customer_name')) {
            $query->whereHas('customer', function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->where('name', 'like', '%' . $request->customer_name . '%')
                         ->orWhere('last_name', 'like', '%' . $request->customer_name . '%')
                         ->orWhere('company_name', 'like', '%' . $request->customer_name . '%');
                });
            });
        }

        // Búsqueda por documento de cliente
        if ($request->has('customer_document')) {
            $query->whereHas('customer', function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->where('dni', 'like', '%' . $request->customer_document . '%')
                         ->orWhere('company_nit', 'like', '%' . $request->customer_document . '%');
                });
            });
        }

        // Búsqueda general por texto (busca en número de reserva, nombre y documento)
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('reservation_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('customer', function($customerQuery) use ($searchTerm) {
                      $customerQuery->where(function($subQ) use ($searchTerm) {
                          $subQ->where('name', 'like', '%' . $searchTerm . '%')
                               ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                               ->orWhere('company_name', 'like', '%' . $searchTerm . '%')
                               ->orWhere('dni', 'like', '%' . $searchTerm . '%')
                               ->orWhere('company_nit', 'like', '%' . $searchTerm . '%');
                      });
                  });
            });
        }

        // Por defecto, mostrar solo reservas de los últimos 30 días si no hay filtros activos
        $hasDateFilter = $request->has('date_from') || $request->has('date_to');
        $hasOtherFilters = $request->has('status') || 
                          $request->has('payment_status') ||
                          $request->has('reservation_type') || 
                          $request->has('room_type_id') ||
                          $request->has('search') ||
                          $request->has('customer_name') ||
                          $request->has('reservation_number') ||
                          $request->has('customer_document') ||
                          $request->has('additional_service_id') ||
                          $request->has('service_package_id');
        
        // Si no hay filtros activos, limitar a últimos 30 días por defecto
        if (!$hasDateFilter && !$hasOtherFilters && !$request->boolean('show_all')) {
            $thirtyDaysAgo = now()->subDays(30)->format('Y-m-d');
            $query->where('check_in_date', '>=', $thirtyDaysAgo);
        }

        // Soporte para paginación
        if ($request->has('per_page') || $request->has('page')) {
            $perPage = $request->input('per_page', 50);
            $reservations = $query->orderBy('check_in_date', 'desc')->paginate($perPage);
            
            // Si se incluyeron facturas del kiosko, agregar facturas pendientes del cliente para reservas checked_in
            if ($request->has('include') && (is_array($request->include) ? in_array('kiosk_invoices', $request->include) : str_contains($request->include, 'kiosk_invoices'))) {
                $reservations->getCollection()->transform(function($reservation) {
                    if ($reservation->status === 'checked_in' && $reservation->customer_id) {
                        $pendingCustomerInvoices = \App\Models\KioskInvoice::where('customer_id', $reservation->customer_id)
                            ->whereHas('payment_type', function($query) {
                                $query->where('credit', true);
                            })
                            ->where('payed', false)
                            ->whereNull('reservation_id')
                            ->with(['payment_type', 'details.kiosk_unit.product'])
                            ->get();

                        if ($pendingCustomerInvoices->count() > 0) {
                            $existingInvoices = $reservation->kioskInvoices;
                            $reservation->setRelation('kioskInvoices', $existingInvoices->merge($pendingCustomerInvoices));
                        }
                    }
                    return $reservation;
                });
            }
            
            return response()->json([
                'data' => $reservations->items(),
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
                'from' => $reservations->firstItem(),
                'to' => $reservations->lastItem(),
            ]);
        }

        $reservations = $query->orderBy('check_in_date', 'desc')->get();
        
        // Si se incluyeron facturas del kiosko, agregar facturas pendientes del cliente para reservas checked_in
        if ($request->has('include') && (is_array($request->include) ? in_array('kiosk_invoices', $request->include) : str_contains($request->include, 'kiosk_invoices'))) {
            $reservations->transform(function($reservation) {
                if ($reservation->status === 'checked_in' && $reservation->customer_id) {
                    $pendingCustomerInvoices = \App\Models\KioskInvoice::where('customer_id', $reservation->customer_id)
                        ->whereHas('payment_type', function($query) {
                            $query->where('credit', true);
                        })
                        ->where('payed', false)
                        ->whereNull('reservation_id')
                        ->with(['payment_type', 'details.kiosk_unit.product'])
                        ->get();

                    if ($pendingCustomerInvoices->count() > 0) {
                        $existingInvoices = $reservation->kioskInvoices;
                        $reservation->setRelation('kioskInvoices', $existingInvoices->merge($pendingCustomerInvoices));
                    }
                }
                return $reservation;
            });
        }

        return response()->json($reservations);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'customer',
            'room',
            'room.roomType',
            'roomType',
            'guests',
            'additionalServices.additionalService',
            'createdBy',
            'childReservations',
            'parentReservation',
            'payments.paymentType',
            'kioskInvoices.payment_type',
            'kioskInvoices.details.kiosk_unit.product',
            'minibarCharges.product',
            'audits.user',
            'promotion',
            'cancellationPolicy'
        ]);

        // Asegurar que las facturas del kiosko se carguen incluso si no están en el eager loading
        if (!$reservation->relationLoaded('kioskInvoices')) {
            $reservation->load([
                'kioskInvoices.payment_type',
                'kioskInvoices.details.kiosk_unit.product'
            ]);
        }

        // Si la reserva está activa (checked_in), también incluir facturas pendientes del cliente
        // que no tienen reservation_id asignado (facturas que deberían estar asociadas a esta reserva)
        if ($reservation->status === 'checked_in') {
            $pendingCustomerInvoices = \App\Models\KioskInvoice::where('customer_id', $reservation->customer_id)
                ->whereHas('payment_type', function($query) {
                    $query->where('credit', true);
                })
                ->where('payed', false)
                ->whereNull('reservation_id')
                ->with(['payment_type', 'details.kiosk_unit.product'])
                ->get();

            // Agregar estas facturas a la relación kioskInvoices
            if ($pendingCustomerInvoices->count() > 0) {
                $existingInvoices = $reservation->kioskInvoices;
                $reservation->setRelation('kioskInvoices', $existingInvoices->merge($pendingCustomerInvoices));
            }
        }

        return response()->json($reservation);
    }

    /**
     * Crear reserva con múltiples habitaciones cuando se supera el aforo
     */
    protected function createMultiRoomReservation(Request $request, $roomTypeId, $totalGuests)
    {
        \Log::info('Iniciando creación de reserva múltiple', [
            'room_type_id' => $roomTypeId,
            'total_guests' => $totalGuests,
            'check_in_date' => $request->check_in_date,
            'check_out_date' => $request->check_out_date,
        ]);

        // Verificar si se proporcionaron habitaciones seleccionadas manualmente
        $selectedRoomIds = $request->has('selected_room_ids') && is_array($request->selected_room_ids) 
            ? $request->selected_room_ids 
            : null;

        if ($selectedRoomIds && count($selectedRoomIds) > 0) {
            // Usar habitaciones seleccionadas manualmente (pueden ser de uno o varios tipos si vienen de "Cualquiera")
            \Log::info('Usando habitaciones seleccionadas manualmente', [
                'selected_room_ids' => $selectedRoomIds,
            ]);

            $selectedRooms = Room::whereIn('id', $selectedRoomIds)->where('active', true)->get();

            if ($selectedRooms->count() !== count($selectedRoomIds)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Algunas habitaciones seleccionadas no existen o no están activas'
                ], 422);
            }

            // Permitir mezcla de tipos cuando se pasan IDs (ej. desde "Cualquiera"); el tipo del grupo es el de la primera
            $roomTypeId = $roomTypeId ?: $selectedRooms->first()->room_type_id;

            // Validar disponibilidad de cada habitación seleccionada
            $unavailableRooms = [];
            foreach ($selectedRooms as $room) {
                if (!$room->isAvailable(
                    $request->check_in_date,
                    $request->check_out_date ?? $request->check_in_date
                )) {
                    $unavailableRooms[] = $room->room_number ?? "Habitación #{$room->id}";
                }
            }

            if (!empty($unavailableRooms)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Las siguientes habitaciones no están disponibles: ' . implode(', ', $unavailableRooms)
                ], 409);
            }

            // Validar que la capacidad total sea suficiente (usar max_capacity si existe)
            $totalCapacity = $selectedRooms->sum(fn ($r) => (int) ($r->max_capacity ?? $r->capacity));
            if ($totalCapacity < $totalGuests) {
                DB::rollBack();
                return response()->json([
                    'message' => "La capacidad total de las habitaciones seleccionadas ({$totalCapacity}) es menor que el número de huéspedes ({$totalGuests})"
                ], 422);
            }

            $availableRooms = $selectedRooms->sortByDesc(fn ($r) => (int) ($r->max_capacity ?? $r->capacity));
        } else {
            // Buscar habitaciones disponibles automáticamente
            $availableRooms = Room::where('room_type_id', $roomTypeId)
                ->where('status', 'available')
                ->where('active', true)
                ->orderBy('capacity', 'desc')
                ->get()
                ->filter(function ($room) use ($request) {
                    return $room->isAvailable(
                        $request->check_in_date,
                        $request->check_out_date ?? $request->check_in_date
                    );
                });

            if ($availableRooms->isEmpty()) {
                \Log::warning('No hay habitaciones disponibles para reserva múltiple', [
                    'room_type_id' => $roomTypeId,
                    'total_guests' => $totalGuests,
                ]);
                return response()->json([
                    'message' => 'No hay suficientes habitaciones disponibles para alojar a todos los huéspedes'
                ], 409);
            }
        }

        // Calcular habitaciones necesarias con distribución inteligente de huéspedes
        $roomsNeeded = [];
        $guests = $request->has('guests') && is_array($request->guests) ? $request->guests : [];
        
        // Mejorar distribución: mantener familias juntas
        $roomsNeeded = $this->distributeGuestsIntelligently(
            $availableRooms,
            $totalGuests,
            $request->adults ?? 0,
            $request->children ?? 0,
            $request->infants ?? 0,
            $guests
        );

        // Validar que todos los huéspedes fueron asignados
        $totalAssigned = array_sum(array_column($roomsNeeded, 'guests_count'));
        if ($totalAssigned < $totalGuests) {
            $remainingGuests = $totalGuests - $totalAssigned;
            \Log::warning('No hay suficientes habitaciones para todos los huéspedes', [
                'remaining_guests' => $remainingGuests,
                'total_guests' => $totalGuests,
                'assigned_guests' => $totalAssigned,
            ]);
            return response()->json([
                'message' => 'No hay suficientes habitaciones disponibles. Faltan ' . $remainingGuests . ' espacios'
            ], 409);
        }

        \Log::info('Habitaciones calculadas para reserva múltiple', [
            'rooms_count' => count($roomsNeeded),
            'rooms' => array_map(function($r) {
                return ['room_id' => $r['room']->id, 'room_number' => $r['room']->room_number, 'guests' => $r['guests_count']];
            }, $roomsNeeded),
        ]);

        // Iniciar transacción para asegurar atomicidad
        DB::beginTransaction();
        try {
            // Reserva principal
            $mainRoom = $roomsNeeded[0];
            
            // Re-verificar disponibilidad justo antes de crear la reserva principal
            $mainRoomFresh = Room::find($mainRoom['room']->id);
            if (!$mainRoomFresh || !$mainRoomFresh->isAvailable(
                $request->check_in_date,
                $request->check_out_date ?? $request->check_in_date
            )) {
                DB::rollBack();
                \Log::warning('Habitación principal ya no está disponible', [
                    'room_id' => $mainRoom['room']->id,
                    'room_number' => $mainRoom['room']->room_number,
                ]);
                return response()->json([
                    'message' => 'La habitación ' . $mainRoom['room']->room_number . ' ya no está disponible. Por favor, intente nuevamente.'
                ], 409);
            }

            $mainReservation = Reservation::create([
                'customer_id' => $request->customer_id,
                'room_id' => $mainRoom['room']->id,
                'room_type_id' => $roomTypeId,
                'reservation_type' => $request->reservation_type,
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'adults' => $mainRoom['adults'],
                'children' => $mainRoom['children'],
                'infants' => $mainRoom['infants'],
                'total_price' => $mainRoom['room']->room_price,
                'deposit_amount' => 0,
                'special_requests' => $request->special_requests,
                'status' => 'confirmed',
                'payment_status' => $request->payment_status ?? 'pending',
                'free_reservation_reason' => $request->free_reservation_reason,
                'free_reservation_reference' => $request->free_reservation_reference,
                'created_by' => $request->user()->id ?? null,
                'is_group_reservation' => true,
                'room_sequence' => 1,
                'contact_channel' => $request->contact_channel,
                'referral_source' => $request->referral_source,
                'social_media_platform' => $request->social_media_platform,
                'campaign_name' => $request->campaign_name,
                'tracking_code' => $request->tracking_code,
                'marketing_notes' => $request->marketing_notes,
            ]);

            // Huéspedes en habitación principal
            $guestsAssigned = 0;
            if (!empty($guests)) {
                foreach ($guests as $index => $guestData) {
                    if ($guestsAssigned >= $mainRoom['guests_count']) {
                        break;
                    }

                    $mainReservation->guests()->create([
                        'first_name' => $guestData['first_name'],
                        'last_name' => $guestData['last_name'],
                        'document_type' => $guestData['document_type'] ?? null,
                        'document_number' => $guestData['document_number'] ?? null,
                        'birth_date' => $guestData['birth_date'] ?? null,
                        'gender' => $guestData['gender'] ?? null,
                        'nationality' => $guestData['nationality'] ?? null,
                        'email' => $guestData['email'] ?? null,
                        'phone' => $guestData['phone'] ?? null,
                        'special_needs' => $guestData['special_needs'] ?? null,
                        'is_primary_guest' => $index === 0,
                        'health_insurance_name' => $guestData['health_insurance_name'] ?? null,
                        'health_insurance_type' => $guestData['health_insurance_type'] ?? null,
                    ]);
                    $guestsAssigned++;
                }
            }

            // Reservas hijas
            $totalPrice = $mainRoom['room']->room_price;
            $childReservations = [];

            for ($i = 1; $i < count($roomsNeeded); $i++) {
                $roomData = $roomsNeeded[$i];

                // Re-verificar disponibilidad justo antes de crear cada reserva hija
                $roomFresh = Room::find($roomData['room']->id);
                if (!$roomFresh || !$roomFresh->isAvailable(
                    $request->check_in_date,
                    $request->check_out_date ?? $request->check_in_date
                )) {
                    DB::rollBack();
                    \Log::warning('Habitación hija ya no está disponible durante creación', [
                        'room_id' => $roomData['room']->id,
                        'room_number' => $roomData['room']->room_number,
                        'sequence' => $i + 1,
                    ]);
                    return response()->json([
                        'message' => 'La habitación ' . $roomData['room']->room_number . ' ya no está disponible. Por favor, intente nuevamente.'
                    ], 409);
                }

                $childReservation = Reservation::create([
                    'customer_id' => $request->customer_id,
                    'room_id' => $roomData['room']->id,
                    'room_type_id' => $roomTypeId,
                    'reservation_type' => $request->reservation_type,
                    'check_in_date' => $request->check_in_date,
                    'check_out_date' => $request->check_out_date,
                    'adults' => $roomData['adults'],
                    'children' => $roomData['children'],
                    'infants' => $roomData['infants'],
                    'total_price' => $roomData['room']->room_price,
                    'deposit_amount' => 0,
                    'special_requests' => $request->special_requests,
                    'status' => 'confirmed',
                    'payment_status' => $request->payment_status ?? 'pending',
                    'free_reservation_reason' => $request->free_reservation_reason,
                    'free_reservation_reference' => $request->free_reservation_reference,
                    'created_by' => $request->user()->id ?? null,
                    'parent_reservation_id' => $mainReservation->id,
                    'is_group_reservation' => true,
                    'room_sequence' => $i + 1,
                    'contact_channel' => $request->contact_channel,
                    'referral_source' => $request->referral_source,
                    'social_media_platform' => $request->social_media_platform,
                    'campaign_name' => $request->campaign_name,
                    'tracking_code' => $request->tracking_code,
                    'marketing_notes' => $request->marketing_notes,
                ]);

                // Huéspedes en reservas hijas
                $guestsForRoom = $roomData['guests_count'];
                for ($j = 0; $j < $guestsForRoom && $guestsAssigned < count($guests); $j++) {
                    $guestData = $guests[$guestsAssigned];
                    $childReservation->guests()->create([
                        'first_name' => $guestData['first_name'],
                        'last_name' => $guestData['last_name'],
                        'document_type' => $guestData['document_type'] ?? null,
                        'document_number' => $guestData['document_number'] ?? null,
                        'birth_date' => $guestData['birth_date'] ?? null,
                        'gender' => $guestData['gender'] ?? null,
                        'nationality' => $guestData['nationality'] ?? null,
                        'email' => $guestData['email'] ?? null,
                        'phone' => $guestData['phone'] ?? null,
                        'special_needs' => $guestData['special_needs'] ?? null,
                        'is_primary_guest' => false,
                        'health_insurance_name' => $guestData['health_insurance_name'] ?? null,
                        'health_insurance_type' => $guestData['health_insurance_type'] ?? null,
                    ]);
                    $guestsAssigned++;
                }

                $totalPrice += $roomData['room']->room_price;
                $childReservations[] = $childReservation;
            }

            // Actualizar precio total de la reserva principal (solo alojamiento por ahora)
            if ($request->has('total_price')) {
                $mainReservation->total_price = $request->total_price;
            } else {
                $mainReservation->total_price = $totalPrice;
            }
            $mainReservation->save();

            // Aplicar servicios adicionales y paquete a la reserva principal con TODOS los huéspedes del grupo
            $chargeableGuests = $totalGuests;
            if ($request->service_package_id) {
                $package = ServicePackage::find($request->service_package_id);
                if ($package && $package->status === 'active') {
                    $this->additionalServiceCalculator->applyPackageToReservation(
                        $mainReservation,
                        $package,
                        $chargeableGuests
                    );
                }
            }
            if ($request->has('additional_service_ids') && is_array($request->additional_service_ids)) {
                foreach ($request->additional_service_ids as $sid) {
                    $svc = AdditionalService::find($sid);
                    if ($svc && $svc->status === 'active' && $this->additionalServiceCalculator->serviceAppliesToReservationType($svc, $request->reservation_type ?? 'room')) {
                        $this->additionalServiceCalculator->addServiceToReservation($mainReservation, $svc, $chargeableGuests);
                    }
                }
            }
            $mainReservation->recomputeFinalPrice();
            $totalPrice = (float) ($mainReservation->final_price ?? $mainReservation->total_price);

            // Confirmar transacción
            DB::commit();

            \Log::info('Reserva múltiple creada exitosamente', [
                'main_reservation_id' => $mainReservation->id,
                'total_rooms' => count($roomsNeeded),
                'total_price' => $totalPrice,
                'child_reservations_count' => count($childReservations),
            ]);

            // Google Calendar (fuera de la transacción, no crítico)
            try {
                $this->googleCalendarService->createEvent($mainReservation);
                foreach ($childReservations as $child) {
                    $this->googleCalendarService->createEvent($child);
                }
            } catch (\Exception $e) {
                \Log::warning('Error creating Google Calendar events: ' . $e->getMessage());
            }

            // Email (fuera de la transacción, no crítico)
            try {
                $this->emailService->sendReservationConfirmation($mainReservation);
            } catch (\Exception $e) {
                \Log::warning('Error sending email: ' . $e->getMessage());
            }

            $mainReservation->load([
                'customer',
                'room',
                'room.roomType',
                'roomType',
                'guests',
                'childReservations.room',
                'childReservations.room.roomType',
                'additionalServices.additionalService',
            ]);

            // Preparar información detallada de habitaciones asignadas
            $roomsAssigned = [];
            $roomsAssigned[] = [
                'room_id' => $mainReservation->room_id,
                'room_number' => $mainReservation->room->room_number ?? 'N/A',
                'guests' => $mainRoom['guests_count'],
                'adults' => $mainRoom['adults'],
                'children' => $mainRoom['children'],
                'infants' => $mainRoom['infants'],
                'price' => $mainRoom['room']->room_price,
                'sequence' => 1,
            ];

            foreach ($childReservations as $index => $child) {
                $roomData = $roomsNeeded[$index + 1];
                $roomsAssigned[] = [
                    'room_id' => $child->room_id,
                    'room_number' => $child->room->room_number ?? 'N/A',
                    'guests' => $roomData['guests_count'],
                    'adults' => $roomData['adults'],
                    'children' => $roomData['children'],
                    'infants' => $roomData['infants'],
                    'price' => $roomData['room']->room_price,
                    'sequence' => $index + 2,
                ];
            }

            // Preparar desglose de precios
            $priceBreakdown = [
                'rooms' => array_map(function($r) {
                    return [
                        'room_number' => $r['room_number'],
                        'price' => $r['price'],
                    ];
                }, $roomsAssigned),
                'subtotal' => $totalPrice,
                'total' => $totalPrice,
            ];

            return response()->json([
                'message' => 'Reserva creada exitosamente con ' . count($roomsNeeded) . ' habitación(es)',
                'main_reservation' => $mainReservation,
                'child_reservations' => $childReservations,
                'total_rooms' => count($roomsNeeded),
                'total_price' => $totalPrice,
                'rooms_assigned' => $roomsAssigned,
                'price_breakdown' => $priceBreakdown,
            ], 201);

        } catch (\Exception $e) {
            // Rollback en caso de error
            DB::rollBack();
            \Log::error('Error creando reserva múltiple', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'room_type_id' => $roomTypeId,
                'total_guests' => $totalGuests,
            ]);
            
            return response()->json([
                'message' => 'Error al crear la reserva múltiple. Por favor, intente nuevamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Ajustar check_out_date para pasadía antes de validar
        if ($request->reservation_type === 'day_pass') {
            $request->merge(['check_out_date' => $request->check_in_date]);
        }

        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'room_id' => 'nullable|exists:rooms,id',
            'room_type_id' => 'nullable|exists:room_types,id',
            'reservation_type' => 'required|in:room,day_pass',
            'check_in_date' => 'required|date',
            'adults' => 'required|integer|min:1',
            'children' => 'integer|min:0',
            'infants' => 'integer|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'deposit_amount' => 'numeric|min:0',
            'payment_status' => 'nullable|in:pending,partial,paid,free,refunded',
            'free_reservation_reason' => 'required_if:payment_status,free|nullable|string|max:500',
            'free_reservation_reference' => 'nullable|string|max:200',
            'special_requests' => 'nullable|string|max:1000',
            'guests' => 'nullable|array',
            'guests.*.first_name' => 'required_with:guests|string',
            'guests.*.last_name' => 'required_with:guests|string',
            'guests.*.document_type' => 'nullable|string',
            'guests.*.document_number' => 'nullable|string',
            'guests.*.birth_date' => 'nullable|date',
            'guests.*.gender' => 'nullable|in:male,female,other',
            'guests.*.nationality' => 'nullable|string',
            'guests.*.email' => 'nullable|email',
            'guests.*.phone' => 'nullable|string',
            'guests.*.special_needs' => 'nullable|string',
            'guests.*.is_primary_guest' => 'nullable|boolean',
            'guests.*.health_insurance_name' => 'nullable|string|max:200',
            'guests.*.health_insurance_type' => 'nullable|in:national,international',
            'contact_channel' => 'nullable|in:whatsapp,facebook,instagram,email,phone,website,walk_in,other',
            'referral_source' => 'nullable|in:social_media,google_search,recommendation,previous_guest,travel_agency,booking_platform,advertisement,other',
            'social_media_platform' => 'nullable|string|max:100',
            'campaign_name' => 'nullable|string|max:200',
            'tracking_code' => 'nullable|string|max:100',
            'marketing_notes' => 'nullable|string|max:1000',
            'additional_service_ids' => 'nullable|array',
            'additional_service_ids.*' => 'exists:additional_services,id',
            'service_package_id' => 'nullable|exists:service_packages,id',
        ];

        // Validación condicional para check_out_date
        if ($request->reservation_type === 'day_pass') {
            // Para pasadía, check_out_date debe ser igual a check_in_date
            $rules['check_out_date'] = 'required|date|same:check_in_date';
        } else {
            // Para habitación, check_out_date debe ser después de check_in_date
            $rules['check_out_date'] = 'required|date|after:check_in_date';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Si es pasadía, validar aforo y ajustar fecha de salida
            if ($request->reservation_type === 'day_pass') {
                // Para pasadía, la fecha de salida es la misma que la de entrada
                $request->merge(['check_out_date' => $request->check_in_date]);
                
                // Validar aforo de pasadía
                $totalGuests = $request->adults + ($request->children ?? 0);
                $dayPassCapacity = \App\Models\DayPassCapacity::getOrCreateForDate($request->check_in_date, 0);
                
                if (!$dayPassCapacity->hasCapacityFor($totalGuests)) {
                    return response()->json([
                        'message' => "No hay capacidad disponible para pasadía el día {$request->check_in_date}. Capacidad disponible: {$dayPassCapacity->available_capacity}, solicitada: {$totalGuests}",
                        'available_capacity' => $dayPassCapacity->available_capacity,
                        'requested_people' => $totalGuests,
                    ], 409);
                }
            } elseif ($request->reservation_type === 'room') {
                $totalGuests = (int) $request->adults + (int) ($request->children ?? 0);
                $hasSelectedRooms = $request->has('selected_room_ids')
                    && is_array($request->selected_room_ids)
                    && count($request->selected_room_ids) > 0;

                // Si el usuario eligió habitaciones manualmente, ir directo a reserva múltiple
                if ($hasSelectedRooms) {
                    DB::rollBack(); // Cerrar transacción actual (vacía); createMultiRoomReservation usa la suya
                    return $this->createMultiRoomReservation(
                        $request,
                        $request->room_type_id ? (int) $request->room_type_id : null,
                        $totalGuests
                    );
                }

                // Asignación automática por room_type_id
                if ($request->room_type_id && !$request->room_id) {
                    $availableRooms = Room::where('room_type_id', $request->room_type_id)
                        ->where('status', 'available')
                        ->where('active', true)
                        ->get()
                        ->filter(function ($room) use ($request) {
                            return $room->isAvailable(
                                $request->check_in_date,
                                $request->check_out_date ?? $request->check_in_date
                            ) && $room->canAccommodate(
                                $request->adults,
                                $request->children ?? 0,
                                0
                            );
                        });

                    if ($availableRooms->isEmpty()) {
                        return response()->json([
                            'message' => 'No hay habitaciones disponibles del tipo seleccionado para las fechas y número de huéspedes'
                        ], 409);
                    }

                    $request->merge(['room_id' => $availableRooms->first()->id]);
                }

                if ($request->room_id) {
                    $room = Room::findOrFail($request->room_id);

                    if ($request->room_type_id && $room->room_type_id != $request->room_type_id) {
                        return response()->json([
                            'message' => 'La habitación seleccionada no corresponde al tipo de habitación especificado'
                        ], 409);
                    }

                    // Verificar si la habitación tiene mantenimientos activos
                    if ($room->hasActiveMaintenance()) {
                        $activeMaintenance = $room->maintenanceRequests()
                            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'on_hold'])
                            ->first();
                        
                        return response()->json([
                            'message' => 'La habitación está en mantenimiento y no puede ser reservada. ' . 
                                       ($activeMaintenance ? "Motivo: {$activeMaintenance->title}" : '')
                        ], 409);
                    }

                    if (!$room->isAvailable(
                        $request->check_in_date,
                        $request->check_out_date ?? $request->check_in_date
                    )) {
                        return response()->json([
                            'message' => 'La habitación no está disponible para las fechas seleccionadas'
                        ], 409);
                    }

                    $totalGuests = $request->adults + ($request->children ?? 0);
                    if (!$room->canAccommodate(
                        $request->adults,
                        $request->children ?? 0,
                        0
                    )) {
                        return $this->createMultiRoomReservation($request, $room->room_type_id, $totalGuests);
                    }
                } elseif ($request->room_type_id) {
                    $totalGuests = $request->adults + ($request->children ?? 0);
                    $availableRooms = Room::where('room_type_id', $request->room_type_id)
                        ->where('status', 'available')
                        ->where('active', true)
                        ->get()
                        ->filter(function ($room) use ($request) {
                            return $room->isAvailable(
                                $request->check_in_date,
                                $request->check_out_date ?? $request->check_in_date
                            );
                        });

                    if ($availableRooms->isEmpty()) {
                        return response()->json([
                            'message' => 'No hay habitaciones disponibles del tipo seleccionado'
                        ], 409);
                    }

                    $maxCapacity = $availableRooms->max('capacity');
                    if ($totalGuests > $maxCapacity) {
                        return $this->createMultiRoomReservation($request, $request->room_type_id, $totalGuests);
                    }
                }
            }

            // Calcular precio usando el servicio mejorado
            $chargeableGuests = $request->adults + ($request->children ?? 0);
            
            // Crear reserva temporal para calcular precio
            $tempReservation = new Reservation([
                'reservation_type' => $request->reservation_type,
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'adults' => $request->adults,
                'children' => $request->children ?? 0,
                'infants' => $request->infants ?? 0,
                'extra_beds' => $request->extra_beds ?? 0,
                'promotion_code' => $request->promotion_code,
                'discount_amount' => $request->discount_amount ?? 0,
                'early_check_in' => $request->early_check_in ?? false,
                'late_check_out' => $request->late_check_out ?? false,
                'early_check_in_fee' => $request->early_check_in_fee ?? 0,
                'late_check_out_fee' => $request->late_check_out_fee ?? 0,
            ]);
            
            if ($request->room_id) {
                $tempReservation->room_id = $request->room_id;
                $tempReservation->setRelation('room', isset($room) ? $room : Room::find($request->room_id));
            } elseif ($request->room_type_id) {
                $tempReservation->room_type_id = $request->room_type_id;
                $tempReservation->setRelation('roomType', RoomType::find($request->room_type_id));
            }

            $priceCalculation = $this->priceCalculator->calculatePrice($tempReservation);
            $calculatedPrice = $priceCalculation['calculated_price'];
            $priceBreakdown = $priceCalculation['price_breakdown'];
            
            // Usar precio manual si se proporciona y hay override, sino usar el calculado
            $totalPrice = ($request->manual_price_override && $request->total_price !== null) 
                ? $request->total_price 
                : $calculatedPrice;

            $reservation = Reservation::create([
                'customer_id' => $request->customer_id,
                'room_id' => $request->reservation_type === 'day_pass' ? null : $request->room_id,
                'room_type_id' => $request->reservation_type === 'day_pass' ? null : ($request->room_type_id ?? ($request->room_id ? Room::find($request->room_id)->room_type_id : null)),
                'reservation_type' => $request->reservation_type,
                'check_in_date' => $request->check_in_date,
                'check_out_date' => $request->check_out_date,
                'adults' => $request->adults,
                'children' => $request->children ?? 0,
                'infants' => $request->infants ?? 0,
                'extra_beds' => $request->extra_beds ?? 0,
                'total_price' => $totalPrice,
                'calculated_price' => $calculatedPrice,
                'manual_price_override' => $request->manual_price_override ?? false,
                'price_breakdown' => $priceBreakdown,
                'promotion_code' => $request->promotion_code,
                'discount_amount' => $request->discount_amount ?? 0,
                'final_price' => $calculatedPrice - ($request->discount_amount ?? 0),
                'deposit_amount' => $request->deposit_amount ?? 0,
                'special_requests' => $request->special_requests,
                'cancellation_policy_id' => $request->cancellation_policy_id,
                'status' => 'confirmed',
                'payment_status' => $request->payment_status ?? 'pending',
                'free_reservation_reason' => $request->free_reservation_reason,
                'free_reservation_reference' => $request->free_reservation_reference,
                'early_check_in' => $request->early_check_in ?? false,
                'late_check_out' => $request->late_check_out ?? false,
                'early_check_in_fee' => $request->early_check_in_fee ?? 0,
                'late_check_out_fee' => $request->late_check_out_fee ?? 0,
                'scheduled_check_in_time' => $request->scheduled_check_in_time,
                'scheduled_check_out_time' => $request->scheduled_check_out_time,
                'created_by' => $request->user()->id ?? null,
                'contact_channel' => $request->contact_channel,
                'referral_source' => $request->referral_source,
                'social_media_platform' => $request->social_media_platform,
                'campaign_name' => $request->campaign_name,
                'tracking_code' => $request->tracking_code,
                'marketing_notes' => $request->marketing_notes,
            ]);

            // Registrar auditoría de creación
            $this->auditService->logCreation($reservation, $request);

            // Programar limpiezas automáticas para check-in y check-out
            if ($reservation->room_id && $reservation->check_in_date && $reservation->check_out_date) {
                $this->scheduleCleaningForReservation($reservation);
            }

            // Actualizar aforo de pasadía si es una reserva de pasadía
            if ($request->reservation_type === 'day_pass') {
                $dayPassCapacity->consumeCapacity($chargeableGuests);
            }

            if ($request->has('guests') && is_array($request->guests)) {
                foreach ($request->guests as $guestData) {
                    $reservation->guests()->create([
                        'first_name' => $guestData['first_name'],
                        'last_name' => $guestData['last_name'],
                        'document_type' => $guestData['document_type'] ?? null,
                        'document_number' => $guestData['document_number'] ?? null,
                        'birth_date' => $guestData['birth_date'] ?? null,
                        'gender' => $guestData['gender'] ?? null,
                        'nationality' => $guestData['nationality'] ?? null,
                        'email' => $guestData['email'] ?? null,
                        'phone' => $guestData['phone'] ?? null,
                        'special_needs' => $guestData['special_needs'] ?? null,
                        'is_primary_guest' => $guestData['is_primary_guest'] ?? false,
                        'health_insurance_name' => $guestData['health_insurance_name'] ?? null,
                        'health_insurance_type' => $guestData['health_insurance_type'] ?? null,
                    ]);
                }
            }

            // Aplicar paquete de servicios o servicios adicionales individuales
            if ($request->service_package_id) {
                $package = ServicePackage::find($request->service_package_id);
                if ($package && $package->status === 'active') {
                    $this->additionalServiceCalculator->applyPackageToReservation(
                        $reservation,
                        $package,
                        $chargeableGuests
                    );
                }
            }
            if ($request->has('additional_service_ids') && is_array($request->additional_service_ids)) {
                foreach ($request->additional_service_ids as $sid) {
                    $svc = AdditionalService::find($sid);
                    if ($svc && $svc->status === 'active' && $this->additionalServiceCalculator->serviceAppliesToReservationType($svc, $request->reservation_type)) {
                        $this->additionalServiceCalculator->addServiceToReservation($reservation, $svc, $chargeableGuests);
                    }
                }
            }
            $reservation->recomputeFinalPrice();

            // Aplicar política de cancelación
            try {
                $this->cancellationService->applyPolicyToReservation($reservation);
            } catch (\Exception $e) {
                \Log::warning('Error applying cancellation policy: ' . $e->getMessage());
            }

            try {
                $this->googleCalendarService->createEvent($reservation);
            } catch (\Exception $e) {
                \Log::warning('Error creating Google Calendar event: ' . $e->getMessage());
            }

            try {
                $this->emailService->sendReservationConfirmation($reservation);
            } catch (\Exception $e) {
                \Log::warning('Error sending email: ' . $e->getMessage());
            }

            DB::commit();

            $reservation->load(['customer', 'room', 'room.roomType', 'guests', 'additionalServices.additionalService']);

            return response()->json($reservation, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Reservation $reservation)
    {
        // Restricción: No se puede editar una reserva en estado checked_in o checked_out
        if ($reservation->status === 'checked_in') {
            return response()->json([
                'message' => 'No se puede editar una reserva que ya tiene check-in realizado. Debe hacer check-out primero.'
            ], 403);
        }

        if ($reservation->status === 'checked_out') {
            return response()->json([
                'message' => 'No se puede editar una reserva que ya tiene check-out realizado.'
            ], 403);
        }

        // Ajustar check_out_date para pasadía antes de validar
        if ($reservation->reservation_type === 'day_pass' && $request->has('check_in_date')) {
            $request->merge(['check_out_date' => $request->check_in_date]);
        }

        $rules = [
            'room_id' => 'nullable|exists:rooms,id',
            'check_in_date' => 'sometimes|date',
            'adults' => 'sometimes|integer|min:1',
            'children' => 'integer|min:0',
            'infants' => 'integer|min:0',
            'total_price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:pending,confirmed,checked_in,checked_out,cancelled',
            'payment_status' => 'sometimes|in:pending,partial,paid,free,refunded',
            'free_reservation_reason' => 'required_if:payment_status,free|nullable|string|max:500',
            'free_reservation_reference' => 'nullable|string|max:200',
            'contact_channel' => 'nullable|in:whatsapp,facebook,instagram,email,phone,website,walk_in,other',
            'referral_source' => 'nullable|in:social_media,google_search,recommendation,previous_guest,travel_agency,booking_platform,advertisement,other',
            'social_media_platform' => 'nullable|string|max:100',
            'campaign_name' => 'nullable|string|max:200',
            'tracking_code' => 'nullable|string|max:100',
            'marketing_notes' => 'nullable|string|max:1000',
        ];

        // Validación condicional para check_out_date
        if ($reservation->reservation_type === 'day_pass') {
            // Para pasadía, check_out_date debe ser igual a check_in_date
            $rules['check_out_date'] = 'nullable|date|same:check_in_date';
        } else {
            // Para habitación, check_out_date debe ser después de check_in_date
            $rules['check_out_date'] = 'nullable|date|after:check_in_date';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Manejar aforo de pasadía si es una reserva de pasadía
            if ($reservation->reservation_type === 'day_pass') {
                $oldDate = $reservation->check_in_date;
                $oldPeople = $reservation->adults + $reservation->children;
                $newDate = $request->check_in_date ?? $reservation->check_in_date;
                $newPeople = ($request->adults ?? $reservation->adults) + ($request->children ?? $reservation->children);
                
                // Si cambió la fecha o el número de personas, actualizar aforo
                if ($oldDate !== $newDate || $oldPeople !== $newPeople) {
                    // Liberar aforo de la fecha anterior
                    $oldCapacity = \App\Models\DayPassCapacity::where('date', $oldDate)->first();
                    if ($oldCapacity) {
                        $oldCapacity->releaseCapacity($oldPeople);
                    }
                    
                    // Validar y consumir aforo de la nueva fecha
                    $newCapacity = \App\Models\DayPassCapacity::getOrCreateForDate($newDate, 0);
                    if (!$newCapacity->hasCapacityFor($newPeople)) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "No hay capacidad disponible para pasadía el día {$newDate}. Capacidad disponible: {$newCapacity->available_capacity}, solicitada: {$newPeople}",
                            'available_capacity' => $newCapacity->available_capacity,
                            'requested_people' => $newPeople,
                        ], 409);
                    }
                    $newCapacity->consumeCapacity($newPeople);
                }
                
                // Si se cancela, liberar aforo
                if ($request->has('status') && $request->status === 'cancelled' && $reservation->status !== 'cancelled') {
                    $capacity = \App\Models\DayPassCapacity::where('date', $newDate)->first();
                    if ($capacity) {
                        $capacity->releaseCapacity($newPeople);
                    }
                }
                
                // Para pasadía, la fecha de salida es la misma que la de entrada
                if ($request->has('check_in_date')) {
                    $request->merge(['check_out_date' => $request->check_in_date]);
                }
                
                // Recalcular precio si cambió fecha, adultos o niños
                if ($request->has('check_in_date') || $request->has('adults') || $request->has('children')) {
                    $updateDate = $request->check_in_date ?? $reservation->check_in_date;
                    $updateAdults = $request->adults ?? $reservation->adults;
                    $updateChildren = $request->children ?? $reservation->children;
                    $dayPassCapacity = \App\Models\DayPassCapacity::getOrCreateForDate($updateDate, 0, 0, 0);
                    $newTotal = $dayPassCapacity->calculatePrice($updateAdults, $updateChildren);
                    $request->merge(['total_price' => $newTotal]);
                }
            } elseif ($request->has('room_id') || $request->has('check_in_date') || $request->has('check_out_date')) {
                $roomId = $request->room_id ?? $reservation->room_id;
                $checkIn = $request->check_in_date ?? $reservation->check_in_date;
                $checkOut = $request->check_out_date ?? $reservation->check_out_date;

                if ($roomId) {
                    $room = Room::findOrFail($roomId);

                    $isAvailable = $room->reservations()
                        ->where('id', '!=', $reservation->id)
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($query) use ($checkIn, $checkOut) {
                            $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                                ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                                ->orWhere(function ($q) use ($checkIn, $checkOut) {
                                    $q->where('check_in_date', '<=', $checkIn)
                                        ->where('check_out_date', '>=', $checkOut);
                                });
                        })
                        ->doesntExist();

                    if (!$isAvailable) {
                        return response()->json([
                            'message' => 'La habitación no está disponible para las fechas seleccionadas'
                        ], 409);
                    }
                }
            }

            $oldValues = $reservation->toArray();
            
            // Detectar cambios en fechas o número de personas (excepto bebés)
            $dateOrPeopleChanged = false;
            if ($request->has('check_in_date') && $request->check_in_date != $reservation->check_in_date) {
                $dateOrPeopleChanged = true;
            }
            if ($request->has('check_out_date') && $request->check_out_date != $reservation->check_out_date) {
                $dateOrPeopleChanged = true;
            }
            if ($request->has('adults') && $request->adults != $reservation->adults) {
                $dateOrPeopleChanged = true;
            }
            if ($request->has('children') && $request->children != $reservation->children) {
                $dateOrPeopleChanged = true;
            }
            // Nota: cambios en infants no cuentan para esta validación
            
            // Si cambió fecha o personas y la reserva está pagada, recalcular precio
            $shouldRecalculatePrice = false;
            if ($dateOrPeopleChanged && $reservation->payment_status === 'paid') {
                $shouldRecalculatePrice = true;
            }
            
            $updateData = $request->only([
                'room_id',
                'check_in_date',
                'check_out_date',
                'adults',
                'children',
                'infants',
                'extra_beds',
                'total_price',
                'status',
                'payment_status',
                'free_reservation_reason',
                'free_reservation_reference',
                'contact_channel',
                'referral_source',
                'social_media_platform',
                'campaign_name',
                'tracking_code',
                'marketing_notes',
                'promotion_code',
                'discount_amount',
                'early_check_in',
                'late_check_out',
                'early_check_in_fee',
                'late_check_out_fee',
            ]);

            // No permitir cancelar si está en checked_in o checked_out
            if (isset($updateData['status']) && $updateData['status'] === 'cancelled') {
                if ($reservation->status === 'checked_in') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'No se puede cancelar una reserva que ya tiene check-in realizado. Debe hacer check-out primero.'
                    ], 403);
                }
                if ($reservation->status === 'checked_out') {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'No se puede cancelar una reserva que ya tiene check-out realizado.'
                    ], 403);
                }
            }
            
            // Si cambió fecha o personas y está pagada, recalcular precio y ajustar estado de pago
            if ($shouldRecalculatePrice) {
                // Crear reserva temporal con los nuevos valores para calcular precio
                $tempReservation = $reservation->replicate();
                $tempReservation->check_in_date = $updateData['check_in_date'] ?? $reservation->check_in_date;
                $tempReservation->check_out_date = $updateData['check_out_date'] ?? $reservation->check_out_date;
                $tempReservation->adults = $updateData['adults'] ?? $reservation->adults;
                $tempReservation->children = $updateData['children'] ?? $reservation->children;
                $tempReservation->infants = $updateData['infants'] ?? $reservation->infants;
                $tempReservation->extra_beds = $updateData['extra_beds'] ?? $reservation->extra_beds;
                $tempReservation->room_id = $updateData['room_id'] ?? $reservation->room_id;
                $tempReservation->room_type_id = $reservation->room_type_id;
                
                // Cargar relaciones necesarias
                if ($tempReservation->room_id) {
                    $tempReservation->setRelation('room', Room::find($tempReservation->room_id));
                }
                if ($tempReservation->room_type_id) {
                    $tempReservation->setRelation('roomType', RoomType::find($tempReservation->room_type_id));
                }
                
                // Recalcular precio
                $priceCalculation = $this->priceCalculator->calculatePrice($tempReservation);
                $newCalculatedPrice = $priceCalculation['calculated_price'];
                $newFinalPrice = $newCalculatedPrice - ($updateData['discount_amount'] ?? $reservation->discount_amount ?? 0);
                
                // Actualizar precio en updateData
                $updateData['calculated_price'] = $newCalculatedPrice;
                $updateData['price_breakdown'] = $priceCalculation['price_breakdown'];
                $updateData['total_price'] = $newCalculatedPrice;
                $updateData['final_price'] = $newFinalPrice;
                
                // Calcular total pagado EXCLUYENDO los pagos a crédito del kiosko (que son deudas pendientes, no pagos reales)
                $totalPaid = $reservation->payments()
                    ->where(function($query) {
                        $query->where('concept', '!=', 'Compra en kiosko (a crédito)')
                              ->orWhereNull('concept');
                    })
                    ->sum('amount');
                
                // Si el nuevo precio es mayor a lo pagado, cambiar estado de pago
                if ($newFinalPrice > $totalPaid) {
                    if ($totalPaid > 0) {
                        $updateData['payment_status'] = 'partial';
                    } else {
                        $updateData['payment_status'] = 'pending';
                    }
                } elseif ($newFinalPrice <= $totalPaid) {
                    // Si el nuevo precio es menor o igual a lo pagado, mantener como pagado
                    $updateData['payment_status'] = 'paid';
                }
            }

            // Si cambió el status, registrar auditoría especial
            if (isset($updateData['status']) && $updateData['status'] !== $reservation->status) {
                $this->auditService->logStatusChange(
                    $reservation,
                    $reservation->status,
                    $updateData['status'],
                    null,
                    $request
                );
            }

            $reservation->update($updateData);

            // Recalcular totales de servicios adicionales si cambiaron fechas o huéspedes
            if ($dateOrPeopleChanged && $reservation->additionalServices()->exists()) {
                $this->additionalServiceCalculator->recalculateReservationAdditionalServices($reservation->fresh());
            }

            // Registrar auditoría de actualización
            $this->auditService->logUpdate($reservation, $oldValues, $updateData, $request);

            // Enviar notificaciones según el tipo de cambio
            if (isset($updateData['status']) && $updateData['status'] === 'cancelled') {
                // Eliminar evento de Google Calendar al cancelar
                if ($reservation->google_calendar_event_id) {
                    try {
                        $this->googleCalendarService->deleteEvent($reservation);
                    } catch (\Exception $e) {
                        \Log::warning('Error deleting Google Calendar event on cancellation: ' . $e->getMessage());
                    }
                }

                // Calcular reembolso y penalización
                try {
                    $refundCalculation = $this->cancellationService->processCancellation(
                        $reservation->fresh(),
                        $request->cancellation_reason ?? $updateData['cancellation_reason'] ?? null
                    );
                    
                    // Recargar reserva con los cálculos actualizados
                    $reservation->refresh();
                } catch (\Exception $e) {
                    \Log::warning('Error calculating refund: ' . $e->getMessage());
                }

                // Enviar notificación de cancelación
                try {
                    $this->notificationService->sendCancellationNotification($reservation->fresh());
                } catch (\Exception $e) {
                    \Log::warning('Error sending cancellation notification: ' . $e->getMessage());
                }
            } else {
                // Enviar notificación de actualización si hay cambios importantes
                $importantFields = ['check_in_date', 'check_out_date', 'room_id', 'total_price', 'adults', 'children'];
                $hasImportantChanges = false;
                $changes = [];
                
                foreach ($importantFields as $field) {
                    if (isset($updateData[$field]) && isset($oldValues[$field])) {
                        $oldValue = $oldValues[$field];
                        $newValue = $updateData[$field];
                        
                        // Formatear valores para mostrar en el email
                        if ($field === 'check_in_date' || $field === 'check_out_date') {
                            $oldValue = $oldValue ? Carbon::parse($oldValue)->format('d/m/Y') : null;
                            $newValue = $newValue ? Carbon::parse($newValue)->format('d/m/Y') : null;
                        } elseif ($field === 'total_price') {
                            $oldValue = $oldValue ? number_format($oldValue, 2) : null;
                            $newValue = $newValue ? number_format($newValue, 2) : null;
                        } elseif ($field === 'room_id') {
                            if ($oldValue) {
                                $oldRoom = Room::find($oldValue);
                                $oldValue = $oldRoom ? $oldRoom->name : $oldValue;
                            }
                            if ($newValue) {
                                $newRoom = Room::find($newValue);
                                $newValue = $newRoom ? $newRoom->name : $newValue;
                            }
                        }
                        
                        if ($oldValue != $newValue) {
                            $hasImportantChanges = true;
                            $changes[$field] = [
                                'old' => $oldValue,
                                'new' => $newValue
                            ];
                        }
                    }
                }
                
                if ($hasImportantChanges) {
                    try {
                        $this->notificationService->sendReservationUpdateNotification($reservation->fresh(), $changes);
                    } catch (\Exception $e) {
                        \Log::warning('Error sending reservation update notification: ' . $e->getMessage());
                    }
                }
            }

            if ($reservation->google_calendar_event_id) {
                try {
                    $this->googleCalendarService->updateEvent($reservation);
                } catch (\Exception $e) {
                    \Log::warning('Error updating Google Calendar event: ' . $e->getMessage());
                }
            }

            // Actualizar limpiezas programadas si cambiaron las fechas
            if ($reservation->wasChanged(['check_in_date', 'check_out_date']) && $reservation->room_id) {
                $this->updateCleaningForReservation($reservation);
            }

            DB::commit();

            $reservation->load(['customer', 'room', 'room.roomType', 'guests', 'additionalServices.additionalService']);

            return response()->json($reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkAvailability(Request $request)
    {
        // Normalizar room_type_id vacío para no fallar validación "exists"
        $input = $request->all();
        if (isset($input['room_type_id']) && (string) $input['room_type_id'] === '') {
            $input['room_type_id'] = null;
        }
        $request->replace($input);

        $validator = Validator::make($request->all(), [
            'check_in_date' => 'required|date',
            'check_out_date' => 'nullable|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
            'children' => 'integer|min:0',
            'infants' => 'integer|min:0',
            'room_type_id' => 'nullable|exists:room_types,id',
            'reservation_type' => 'nullable|in:room,day_pass',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $reservationType = $request->reservation_type ?? 'room';
        $checkIn = Carbon::parse($request->check_in_date);
        // Para pasadía, la fecha de salida es la misma que la de entrada
        $checkOut = ($reservationType === 'day_pass') 
            ? $checkIn->copy() 
            : ($request->check_out_date ? Carbon::parse($request->check_out_date) : $checkIn->copy()->addDay());
        
        // Para capacidad contar solo adultos y niños; los bebés no ocupan cama
        $totalGuests = $request->adults + ($request->children ?? 0);

        // Si es pasadía, verificar aforo diario
        if ($reservationType === 'day_pass') {
            $dayPassCapacity = \App\Models\DayPassCapacity::getOrCreateForDate($checkIn->format('Y-m-d'), 0, 0, 0);
            $available = $dayPassCapacity->hasCapacityFor($totalGuests);
            $adults = $request->adults ?? 1;
            $children = $request->children ?? 0;
            $estimatedTotal = $dayPassCapacity->calculatePrice($adults, $children);

            return response()->json([
                'reservation_type' => 'day_pass',
                'available' => $available,
                'date' => $checkIn->format('Y-m-d'),
                'max_capacity' => $dayPassCapacity->max_capacity,
                'consumed_capacity' => $dayPassCapacity->consumed_capacity,
                'available_capacity' => $dayPassCapacity->available_capacity,
                'requested_people' => $totalGuests,
                'adult_price' => $dayPassCapacity->adult_price,
                'child_price' => $dayPassCapacity->child_price,
                'estimated_total' => $estimatedTotal,
                'available_rooms' => [],
                'rooms_by_type' => [],
                'count' => $available ? 1 : 0,
                'total_guests' => $totalGuests,
                'check_in' => $checkIn->format('Y-m-d'),
                'check_out' => $checkIn->format('Y-m-d'),
            ]);
        }

        // Capacidad efectiva: max_capacity si existe, sino capacity (para respetar "capacidad máxima" de cada habitación)
        $getEffectiveCapacity = function ($room) {
            return (int) ($room->max_capacity ?? $room->capacity);
        };

        // 1) Todas las habitaciones activas (disponibilidad real la decide isAvailable por fechas)
        $querySingle = Room::where('active', true);
        if ($request->room_type_id) {
            $querySingle->where('room_type_id', $request->room_type_id);
        }
        $allAvailable = $querySingle->with('roomType')->get()->filter(function ($room) use ($checkIn, $checkOut) {
            return $room->isAvailable($checkIn, $checkOut);
        });

        $availableRooms = $allAvailable->filter(function ($room) use ($totalGuests, $getEffectiveCapacity) {
            return $getEffectiveCapacity($room) >= $totalGuests;
        });

        $multiRoomRequired = false;
        $multiRoomRooms = collect();

        // 2) Si no hay ninguna habitación sola que alcance, buscar combinación de varias
        $pickFrom = function ($rooms) use ($totalGuests, $getEffectiveCapacity) {
            $coll = $rooms instanceof \Illuminate\Support\Collection ? $rooms : collect($rooms);
            $sorted = $coll->sortByDesc(function ($room) use ($getEffectiveCapacity) {
                return $getEffectiveCapacity($room);
            });
            $chosen = collect();
            $sum = 0;
            foreach ($sorted as $room) {
                $chosen->push($room);
                $sum += $getEffectiveCapacity($room);
                if ($sum >= $totalGuests) {
                    return $chosen;
                }
            }
            return collect();
        };

        if ($availableRooms->isEmpty()) {
            if ($request->room_type_id) {
                $multiRoomRooms = $pickFrom($allAvailable->where('room_type_id', (int) $request->room_type_id));
                $multiRoomRequired = $multiRoomRooms->isNotEmpty();
            } else {
                // Primero intentar por tipo (misma familia de habitaciones)
                $byType = $allAvailable->groupBy('room_type_id');
                foreach ($byType as $roomTypeId => $rooms) {
                    $multiRoomRooms = $pickFrom($rooms);
                    if ($multiRoomRooms->isNotEmpty()) {
                        $multiRoomRequired = true;
                        break;
                    }
                }
                // Si con un solo tipo no alcanza, combinar todas las habitaciones disponibles (varios tipos)
                if (!$multiRoomRequired && $allAvailable->isNotEmpty()) {
                    $multiRoomRooms = $pickFrom($allAvailable);
                    $multiRoomRequired = $multiRoomRooms->isNotEmpty();
                }
            }
            if ($multiRoomRequired) {
                $availableRooms = $multiRoomRooms;
            }
        }

        $roomsByType = $availableRooms->groupBy('room_type_id')->map(function ($rooms) use ($getEffectiveCapacity) {
            $roomType = $rooms->first()->roomType;
            return [
                'room_type' => $roomType,
                'rooms' => $rooms->map(function ($room) use ($getEffectiveCapacity) {
                    return [
                        'id' => $room->id,
                        'number' => $room->number,
                        'name' => $room->name,
                        'display_name' => $room->display_name,
                        'capacity' => $room->capacity,
                        'max_capacity' => $room->max_capacity,
                        'effective_capacity' => $getEffectiveCapacity($room),
                        'room_price' => $room->room_price,
                        'description' => $room->description,
                    ];
                }),
                'count' => $rooms->count(),
                'min_price' => $rooms->min('room_price'),
                'max_price' => $rooms->max('room_price'),
            ];
        });

        $roomsList = $availableRooms->isEmpty()
            ? []
            : \Illuminate\Database\Eloquent\Collection::make($availableRooms->all())->load('roomType')->values()->toArray();

        return response()->json([
            'reservation_type' => 'room',
            'available_rooms' => $roomsList,
            'rooms_by_type' => $roomsByType,
            'count' => $availableRooms->count(),
            'total_guests' => $totalGuests,
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
            'multi_room_required' => $multiRoomRequired,
            'suggested_room_type_id' => $multiRoomRequired && $availableRooms->isNotEmpty()
                ? $availableRooms->first()->room_type_id
                : null,
        ]);
    }

    /**
     * Obtener habitaciones disponibles para selección manual en reservas múltiples
     */
    public function getAvailableRoomsForSelection(Request $request)
    {
        $input = $request->all();
        if (isset($input['room_type_id']) && (string) $input['room_type_id'] === '') {
            $input['room_type_id'] = null;
        }
        $request->replace($input);

        $request->validate([
            'room_type_id' => 'nullable|exists:room_types,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'exclude_reservation_id' => 'nullable|exists:reservations,id',
        ]);

        $checkIn = Carbon::parse($request->check_in_date);
        $checkOut = Carbon::parse($request->check_out_date);

        // Todas las habitaciones activas (del tipo indicado o de cualquier tipo si no se indica)
        $query = Room::where('active', true);
        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Si se excluye una reserva, no incluir sus habitaciones actuales
        if ($request->exclude_reservation_id) {
            $excludeReservation = Reservation::findOrFail($request->exclude_reservation_id);
            $excludeRoomIds = [$excludeReservation->room_id];
            if ($excludeReservation->childReservations) {
                $excludeRoomIds = array_merge(
                    $excludeRoomIds,
                    $excludeReservation->childReservations->pluck('room_id')->toArray()
                );
            }
            $excludeRoomIds = array_filter($excludeRoomIds);
            if (!empty($excludeRoomIds)) {
                $query->whereNotIn('id', $excludeRoomIds);
            }
        }

        $rooms = $query->with('roomType')->get()->filter(function ($room) use ($checkIn, $checkOut) {
            return $room->isAvailable($checkIn, $checkOut);
        });

        $getEffectiveCapacity = fn ($r) => (int) ($r->max_capacity ?? $r->capacity);

        return response()->json([
            'rooms' => $rooms->map(function ($room) use ($getEffectiveCapacity) {
                return [
                    'id' => $room->id,
                    'room_number' => $room->number ?? $room->name ?? (string) $room->id,
                    'name' => $room->name,
                    'display_name' => $room->display_name ?? $room->name ?? $room->number ?? 'Habitación #' . $room->id,
                    'capacity' => $room->capacity,
                    'max_capacity' => $room->max_capacity,
                    'effective_capacity' => $getEffectiveCapacity($room),
                    'room_price' => $room->room_price,
                    'room_type' => $room->roomType ? [
                        'id' => $room->roomType->id,
                        'name' => $room->roomType->name,
                    ] : null,
                    'status' => $room->status,
                ];
            })->values(),
            'total_capacity' => $rooms->sum($getEffectiveCapacity),
            'count' => $rooms->count(),
        ]);
    }

    /**
     * Reasignar habitación en una reserva múltiple
     */
    public function changeRoom(Request $request, Reservation $reservation)
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
        ]);

        // Validar que la reserva sea parte de un grupo
        if (!$reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            return response()->json([
                'message' => 'Esta reserva no es parte de un grupo de reservas múltiples'
            ], 422);
        }

        // Validar que la reserva no esté en check-out
        if ($reservation->status === 'checked_out') {
            return response()->json([
                'message' => 'No se puede cambiar la habitación de una reserva con check-out realizado'
            ], 422);
        }

        $newRoom = Room::findOrFail($request->room_id);

        // Validar que la nueva habitación esté disponible en las fechas de la reserva
        if (!$newRoom->isAvailable($reservation->check_in_date, $reservation->check_out_date)) {
            return response()->json([
                'message' => 'La habitación seleccionada no está disponible para las fechas de la reserva'
            ], 409);
        }

        // Validar que la nueva habitación pueda alojar a los huéspedes
        if (!$newRoom->canAccommodate($reservation->adults, $reservation->children, $reservation->infants)) {
            return response()->json([
                'message' => 'La habitación seleccionada no tiene capacidad suficiente para los huéspedes de esta reserva'
            ], 422);
        }

        // Validar que sea del mismo tipo (opcional, pero recomendado)
        if ($reservation->room_type_id && $newRoom->room_type_id !== $reservation->room_type_id) {
            return response()->json([
                'message' => 'La nueva habitación debe ser del mismo tipo que la reserva original'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldRoomId = $reservation->room_id;
            $oldRoom = $reservation->room;

            // Actualizar la reserva con la nueva habitación
            $reservation->update([
                'room_id' => $newRoom->id,
            ]);

            // Recalcular precio si es necesario
            if ($newRoom->room_price != ($oldRoom->room_price ?? 0)) {
                $priceCalculation = $this->priceCalculator->calculatePrice($reservation->fresh());
                $reservation->update([
                    'total_price' => $priceCalculation['calculated_price'],
                    'calculated_price' => $priceCalculation['calculated_price'],
                    'price_breakdown' => $priceCalculation['price_breakdown'],
                ]);
                
                // Si es una reserva hija, actualizar el precio total de la reserva principal
                if ($reservation->parent_reservation_id) {
                    $parentReservation = Reservation::find($reservation->parent_reservation_id);
                    if ($parentReservation) {
                        $parentTotal = $parentReservation->total_price - ($oldRoom->room_price ?? 0) + $newRoom->room_price;
                        $parentReservation->update(['total_price' => $parentTotal]);
                    }
                }
            }

            // Registrar auditoría
            $this->auditService->logUpdate(
                $reservation,
                ['room_id' => $oldRoomId],
                ['room_id' => $newRoom->id],
                $request,
                "Habitación cambiada de " . ($oldRoom->room_number ?? "Habitación #{$oldRoomId}") . " a {$newRoom->room_number}"
            );

            // Actualizar Google Calendar si existe evento
            if ($reservation->google_calendar_event_id) {
                try {
                    $this->googleCalendarService->updateEvent($reservation->fresh());
                } catch (\Exception $e) {
                    \Log::warning('Error updating Google Calendar event after room change: ' . $e->getMessage());
                }
            }

            DB::commit();

            \Log::info('Habitación reasignada exitosamente', [
                'reservation_id' => $reservation->id,
                'old_room_id' => $oldRoomId,
                'new_room_id' => $newRoom->id,
            ]);

            $reservation->load(['room', 'room.roomType', 'customer']);

            return response()->json([
                'message' => 'Habitación reasignada exitosamente',
                'reservation' => $reservation,
                'old_room' => $oldRoom ? [
                    'id' => $oldRoom->id,
                    'room_number' => $oldRoom->room_number,
                ] : null,
                'new_room' => [
                    'id' => $newRoom->id,
                    'room_number' => $newRoom->room_number,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error reasignando habitación', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al reasignar la habitación. Por favor, intente nuevamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function generateCertificate(Reservation $reservation)
    {
        $certificate = $this->certificateService->generateCertificate($reservation);

        return response()->json([
            'message' => 'Certificado generado exitosamente',
            'certificate' => $certificate
        ]);
    }

    public function downloadCertificate(Reservation $reservation)
    {
        $path = $this->certificateService->getCertificatePath($reservation);

        if (!\Storage::exists($path)) {
            $this->certificateService->generateCertificate($reservation);
        }

        $filename = "reserva-{$reservation->reservation_number}.pdf";
        
        return \Storage::download($path, $filename);
    }

    /**
     * Descargar certificado de checkout
     */
    public function downloadCheckoutCertificate(Reservation $reservation)
    {
        $path = $this->certificateService->getCheckoutCertificatePath($reservation);

        if (!\Storage::exists($path)) {
            $this->certificateService->generateCheckoutCertificate($reservation);
            $path = $this->certificateService->getCheckoutCertificatePath($reservation);
        }

        $filename = "checkout-{$reservation->reservation_number}.pdf";
        
        return \Storage::download($path, $filename);
    }

    public function resendEmail(Reservation $reservation)
    {
        try {
            $this->emailService->sendReservationConfirmation($reservation);
            return response()->json(['message' => 'Email enviado exitosamente']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar el email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reenviar certificado de checkout por email
     */
    public function resendCheckoutEmail(Reservation $reservation)
    {
        // Validar que la reserva tenga checkout realizado
        if ($reservation->status !== 'checked_out') {
            return response()->json([
                'message' => 'Solo se puede reenviar el certificado de checkout para reservas con check-out realizado.'
            ], 422);
        }

        try {
            // Generar o obtener el certificado de checkout
            $checkoutCertificate = $this->certificateService->generateCheckoutCertificate($reservation);
            
            // Enviar email con el certificado
            $this->emailService->sendCheckoutConfirmation($reservation, $checkoutCertificate);
            
            return response()->json(['message' => 'Certificado de checkout enviado exitosamente']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar el certificado de checkout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function marketingReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'group_by' => 'nullable|in:contact_channel,referral_source,campaign_name,social_media_platform',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $query = Reservation::query();

        if ($request->date_from) {
            $query->whereDate('check_in_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('check_in_date', '<=', $request->date_to);
        }

        $reservations = $query->with('customer')->get();

        $groupBy = $request->group_by ?? 'contact_channel';

        $grouped = $reservations->groupBy($groupBy)->map(function ($group, $key) {
            return [
                'name' => $key ?? 'No especificado',
                'count' => $group->count(),
                'total_revenue' => $group->sum('total_price'),
                'average_revenue' => $group->avg('total_price'),
                'reservations' => $group->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'reservation_number' => $reservation->reservation_number,
                        'check_in_date' => $reservation->check_in_date,
                        'total_price' => $reservation->total_price,
                        'customer' => $reservation->customer->display_name,
                    ];
                })
            ];
        })->values();

        $stats = [
            'total_reservations' => $reservations->count(),
            'total_revenue' => $reservations->sum('total_price'),
            'average_revenue' => $reservations->avg('total_price'),
            'by_contact_channel' => $reservations->groupBy('contact_channel')->map->count(),
            'by_referral_source' => $reservations->groupBy('referral_source')->map->count(),
            'by_campaign' => $reservations->whereNotNull('campaign_name')
                ->groupBy('campaign_name')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'revenue' => $group->sum('total_price')
                    ];
                }),
        ];

        return response()->json([
            'grouped_data' => $grouped,
            'statistics' => $stats,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'group_by' => $groupBy,
            ]
        ]);
    }

    public function destroy(Reservation $reservation)
    {
        // Restricción: No se puede eliminar una reserva en estado checked_in o checked_out
        if ($reservation->status === 'checked_in') {
            return response()->json([
                'message' => 'No se puede eliminar una reserva que ya tiene check-in realizado. Debe hacer check-out primero.'
            ], 403);
        }

        if ($reservation->status === 'checked_out') {
            return response()->json([
                'message' => 'No se puede eliminar una reserva que ya tiene check-out realizado.'
            ], 403);
        }

        // Restricción: No se puede eliminar una reserva pagada
        if ($reservation->payment_status === 'paid') {
            return response()->json([
                'message' => 'No se puede eliminar una reserva que ya está pagada.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Si es una reserva de pasadía, liberar el aforo
            if ($reservation->reservation_type === 'day_pass') {
                $totalGuests = $reservation->adults + $reservation->children;
                $capacity = \App\Models\DayPassCapacity::where('date', $reservation->check_in_date)->first();
                if ($capacity) {
                    $capacity->releaseCapacity($totalGuests);
                }
            }

            if ($reservation->google_calendar_event_id) {
                try {
                    $this->googleCalendarService->deleteEvent($reservation);
                } catch (\Exception $e) {
                    \Log::warning('Error deleting Google Calendar event: ' . $e->getMessage());
                }
            }

            // Registrar auditoría antes de eliminar
            $this->auditService->log('deleted', $reservation, $reservation->toArray(), null, 'Reserva eliminada', auth()->id(), request());

            $reservation->delete();

            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar la reserva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar pago a una reserva
     */
    public function addPayment(Request $request, Reservation $reservation)
    {
        // Mapeo de payment_method (ENUM antiguo) a nombres de PaymentType
        $paymentMethodMap = [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'check' => 'Cheque',
            'other' => 'Otro'
        ];

        // Si se envía payment_method (compatibilidad hacia atrás), convertirlo a payment_type_id
        if ($request->has('payment_method') && !$request->has('payment_type_id')) {
            $paymentMethod = $request->payment_method;
            if (isset($paymentMethodMap[$paymentMethod])) {
                $paymentType = \App\Models\PaymentType::where('name', $paymentMethodMap[$paymentMethod])->first();
                if ($paymentType) {
                    $request->merge(['payment_type_id' => $paymentType->id]);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'concept' => 'nullable|string|max:200',
            'payment_type_id' => 'required|exists:payment_types,id',
            'payment_reference' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Validar que no haya un pago duplicado reciente (últimos 5 segundos)
        // Esto previene doble clics o envíos accidentales múltiples
        $recentDuplicate = \App\Models\ReservationPayment::where('reservation_id', $reservation->id)
            ->where('amount', $request->amount)
            ->where('concept', $request->concept ?? '')
            ->where('payment_type_id', $request->payment_type_id)
            ->where('payment_reference', $request->payment_reference ?? '')
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();

        if ($recentDuplicate) {
            return response()->json([
                'message' => 'Este pago ya fue registrado recientemente. Por favor, verifica la lista de pagos.',
                'error' => 'duplicate_payment'
            ], 409); // 409 Conflict
        }

        DB::beginTransaction();
        try {
            // Calcular total ya pagado EXCLUYENDO los pagos a crédito del kiosko (que son deudas pendientes, no pagos reales)
            $totalPaid = $reservation->payments()
                ->where(function($query) {
                    $query->where('concept', '!=', 'Compra en kiosko (a crédito)')
                          ->orWhereNull('concept');
                })
                ->sum('amount');
            $finalPrice = $reservation->final_price ?? $reservation->total_price;
            $reservationBalance = max(0, $finalPrice - $totalPaid);

            // Calcular facturas pendientes del kiosko
            // Incluir facturas asociadas directamente a la reserva
            $pendingKioskInvoices = $reservation->kioskInvoices()
                ->whereHas('payment_type', function ($query) {
                    $query->where('credit', true);
                })
                ->where('payed', false)
                ->with('details')
                ->get();
            
            // Si la reserva está activa (checked_in), también incluir facturas pendientes del cliente
            // que no tienen reservation_id asignado (facturas que deberían estar asociadas a esta reserva)
            if ($reservation->status === 'checked_in') {
                $pendingCustomerInvoices = \App\Models\KioskInvoice::where('customer_id', $reservation->customer_id)
                    ->whereHas('payment_type', function($query) {
                        $query->where('credit', true);
                    })
                    ->where('payed', false)
                    ->whereNull('reservation_id')
                    ->with('details')
                    ->get();
                
                // Combinar ambas listas de facturas pendientes
                $pendingKioskInvoices = $pendingKioskInvoices->merge($pendingCustomerInvoices);
            }
            
            $totalPendingKiosk = $pendingKioskInvoices->sum(function ($invoice) {
                return $invoice->details->sum('price');
            });

            // Calcular cuánto se ha pagado adicional a la reserva (para cargos a habitación)
            // Si el total pagado es mayor que el precio de la reserva, la diferencia es lo que se pagó para cargos a habitación
            $paidForRoomCharges = max(0, $totalPaid - $finalPrice);
            
            // El saldo pendiente de cargos a habitación es el total de cargos menos lo que ya se pagó
            $remainingRoomCharges = max(0, $totalPendingKiosk - $paidForRoomCharges);

            // El saldo pendiente total incluye la reserva + saldo pendiente de cargos a habitación
            $totalPending = $reservationBalance + $remainingRoomCharges;

            // Validar que el pago no exceda el saldo pendiente total (reserva + kiosko)
            // IMPORTANTE: Validar que el pago individual no exceda el saldo pendiente
            if ($request->amount > $totalPending) {
                $formattedAmount = number_format($request->amount, 2);
                $formattedTotalPending = number_format($totalPending, 2);
                $formattedReservationBalance = number_format($reservationBalance, 2);
                $formattedPendingKiosk = number_format($totalPendingKiosk, 2);
                
                $formattedRemainingRoomCharges = number_format($remainingRoomCharges, 2);
                
                return response()->json([
                    'message' => "El monto del pago ({$formattedAmount}) excede el saldo pendiente total ({$formattedTotalPending}). Puede pagar hasta {$formattedTotalPending}: {$formattedReservationBalance} de la reserva" . ($remainingRoomCharges > 0 ? " y {$formattedRemainingRoomCharges} de cargos a habitación pendientes" : "") . ".",
                    'total_price' => $finalPrice,
                    'total_paid' => $totalPaid,
                    'reservation_balance' => $reservationBalance,
                    'total_kiosk_charges' => $totalPendingKiosk,
                    'paid_for_room_charges' => $paidForRoomCharges,
                    'remaining_room_charges' => $remainingRoomCharges,
                    'total_pending' => $totalPending,
                    'payment_amount' => $request->amount,
                    'max_payment_allowed' => $totalPending
                ], 422);
            }
            
            // Validar que el total acumulado de pagos (incluyendo este nuevo pago) no exceda el total debido
            // Calcular el total que se debería pagar: precio de reserva + facturas del kiosko
            $totalDue = $finalPrice + $totalPendingKiosk;
            $totalPaidAfterThisPayment = $totalPaid + $request->amount;
            
            if ($totalPaidAfterThisPayment > $totalDue) {
                $excess = $totalPaidAfterThisPayment - $totalDue;
                $formattedExcess = number_format($excess, 2);
                $formattedTotalDue = number_format($totalDue, 2);
                $formattedTotalPaidAfter = number_format($totalPaidAfterThisPayment, 2);
                
                return response()->json([
                    'message' => "El pago excedería el total debido. El total a pagar es {$formattedTotalDue} (reserva: {$finalPrice} + kiosko: {$totalPendingKiosk}), pero con este pago se pagarían {$formattedTotalPaidAfter}, excediendo en {$formattedExcess}.",
                    'total_price' => $finalPrice,
                    'total_paid' => $totalPaid,
                    'pending_kiosk_invoices' => $totalPendingKiosk,
                    'remaining_room_charges' => $remainingRoomCharges,
                    'total_due' => $totalDue,
                    'total_paid_after_payment' => $totalPaidAfterThisPayment,
                    'excess_amount' => $excess,
                    'payment_amount' => $request->amount,
                    'max_payment_allowed' => $totalPending
                ], 422);
            }

            $payment = ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $request->amount,
                'concept' => $request->concept,
                'payment_type_id' => $request->payment_type_id,
                'payment_reference' => $request->payment_reference,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Actualizar estado de pago de la reserva (excluyendo pagos a crédito del kiosko)
            $newTotalPaid = $reservation->payments()
                ->where(function($query) {
                    $query->where('concept', '!=', 'Compra en kiosko (a crédito)')
                          ->orWhereNull('concept');
                })
                ->sum('amount');

            // Verificar si hay facturas pendientes del kiosko (cargos a habitación)
            // Incluir facturas asociadas directamente a la reserva
            $pendingKioskInvoices = $reservation->kioskInvoices()
                ->whereHas('payment_type', function ($query) {
                    $query->where('credit', true);
                })
                ->where('payed', false)
                ->with('details')
                ->get();
            
            // Si la reserva está activa (checked_in), también incluir facturas pendientes del cliente
            // que no tienen reservation_id asignado (facturas que deberían estar asociadas a esta reserva)
            if ($reservation->status === 'checked_in') {
                $pendingCustomerInvoices = \App\Models\KioskInvoice::where('customer_id', $reservation->customer_id)
                    ->whereHas('payment_type', function($query) {
                        $query->where('credit', true);
                    })
                    ->where('payed', false)
                    ->whereNull('reservation_id')
                    ->with('details')
                    ->get();
                
                // Combinar ambas listas de facturas pendientes
                $pendingKioskInvoices = $pendingKioskInvoices->merge($pendingCustomerInvoices);
            }
            
            $totalPendingKiosk = $pendingKioskInvoices->sum(function ($invoice) {
                return $invoice->details->sum('price');
            });

            // REGLAS DE ESTADO DE PAGO:
            // 1. Si NO hay cargos a habitación pendientes:
            //    - Si el total pagado >= precio reserva → 'paid'
            //    - Si el total pagado > 0 pero < precio reserva → 'partial'
            //    - Si el total pagado = 0 → 'pending'
            // 2. Si HAY cargos a habitación pendientes:
            //    - Siempre 'partial' (incluso si la reserva está pagada)
            //    - Esto permite que se habilite el botón de agregar pago para pagar los cargos
            
            if ($totalPendingKiosk > 0) {
                // Hay cargos a habitación pendientes → siempre 'partial'
                // Esto permite que el frontend muestre el botón de agregar pago
                $reservation->payment_status = 'partial';
            } else {
                // No hay cargos a habitación pendientes
                if ($newTotalPaid >= $finalPrice) {
                    $reservation->payment_status = 'paid';
                } elseif ($newTotalPaid > 0) {
                    $reservation->payment_status = 'partial';
                } else {
                    $reservation->payment_status = 'pending';
                }
            }

            $reservation->save();

            // Obtener el nombre del método de pago para la auditoría
            $paymentType = \App\Models\PaymentType::find($request->payment_type_id);
            $paymentMethodName = $paymentType ? $paymentType->name : 'Desconocido';

            // Registrar auditoría
            $this->auditService->logPayment($reservation, $request->amount, $paymentMethodName, $request->notes, $request);

            // Calcular totales para el correo
            // Nota: final_price ya incluye los servicios adicionales según el método recomputeFinalPrice
            $finalPrice = $reservation->final_price ?? $reservation->total_price;
            $totalPendingKiosk = $pendingKioskInvoices->sum(function ($invoice) {
                return $invoice->details->sum('price');
            });
            // El total debido es: precio de reserva (que ya incluye servicios adicionales) + compras kiosko pendientes
            $totalDue = $finalPrice + $totalPendingKiosk;
            $newBalance = max(0, $totalDue - $newTotalPaid);

            // Cargar relaciones necesarias para el correo
            $pendingKioskInvoices->load(['details.kiosk_unit.product', 'payment_type']);
            $payment->load('paymentType');

            // Enviar correo de confirmación de pago
            try {
                $this->emailService->sendPaymentConfirmation(
                    $reservation,
                    $payment,
                    $pendingKioskInvoices,
                    $newTotalPaid,
                    $totalDue,
                    $newBalance
                );
            } catch (\Exception $e) {
                // Log del error pero no interrumpir el flujo del pago
                \Log::error("Error enviando correo de confirmación de pago: " . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'message' => 'Pago registrado exitosamente',
                'payment' => $payment->load('paymentType'),
                'reservation' => $reservation->fresh(['payments.paymentType', 'customer']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar servicio adicional a una reserva
     */
    public function addAdditionalService(Request $request, Reservation $reservation)
    {
        if (in_array($reservation->status, ['checked_in', 'checked_out'], true)) {
            return response()->json([
                'message' => 'No se pueden modificar servicios adicionales en una reserva con check-in o check-out realizado.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'additional_service_id' => 'required|exists:additional_services,id',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $svc = AdditionalService::findOrFail($request->additional_service_id);
        if ($svc->status !== 'active') {
            return response()->json(['message' => 'El servicio no está activo.'], 422);
        }
        if (!$this->additionalServiceCalculator->serviceAppliesToReservationType($svc, $reservation->reservation_type)) {
            return response()->json(['message' => 'Este servicio no aplica para el tipo de reserva (habitación o pasadía).'], 422);
        }
        if ($reservation->additionalServices()->where('additional_service_id', $svc->id)->exists()) {
            return response()->json(['message' => 'Este servicio ya está agregado a la reserva.'], 422);
        }

        // Para reservas con varias habitaciones, usar el total de huéspedes del grupo
        $chargeableGuests = null;
        if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            $reservation->loadMissing(['childReservations']);
            $chargeableGuests = $reservation->adults + $reservation->children
                + $reservation->childReservations->sum(fn ($r) => $r->adults + $r->children);
            $chargeableGuests = max(1, (int) $chargeableGuests);
        }

        $ras = $this->additionalServiceCalculator->addServiceToReservation($reservation, $svc, $chargeableGuests);
        $reservation->recomputeFinalPrice();
        $reservation->load(['additionalServices.additionalService']);

        return response()->json([
            'message' => 'Servicio agregado.',
            'reservation' => $reservation,
            'item' => $ras,
        ], 201);
    }

    /**
     * Quitar servicio adicional de una reserva
     */
    public function removeAdditionalService(Reservation $reservation, ReservationAdditionalService $reservationAdditionalService)
    {
        if (in_array($reservation->status, ['checked_in', 'checked_out'], true)) {
            return response()->json([
                'message' => 'No se pueden modificar servicios adicionales en una reserva con check-in o check-out realizado.',
            ], 403);
        }

        if ((int) $reservationAdditionalService->reservation_id !== (int) $reservation->id) {
            return response()->json(['message' => 'El servicio no pertenece a esta reserva.'], 422);
        }

        $reservationAdditionalService->delete();
        $reservation->recomputeFinalPrice();
        $reservation->load(['additionalServices.additionalService']);

        return response()->json([
            'message' => 'Servicio quitado.',
            'reservation' => $reservation,
        ]);
    }

    /**
     * Obtener historial de auditoría de una reserva
     */
    public function getAuditHistory(Reservation $reservation)
    {
        $audits = $reservation->audits()->with('user')->orderBy('created_at', 'desc')->get();
        return response()->json($audits);
    }

    /**
     * Realizar check-in de una reserva
     */
    public function checkIn(Request $request, Reservation $reservation)
    {
        // Validar que la reserva esté en estado confirmed
        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Solo se puede hacer check-in de reservas confirmadas. Estado actual: ' . $reservation->status
            ], 422);
        }

        // Validar que la fecha de check-in no sea anterior a la fecha de reserva
        $checkInDate = Carbon::parse($reservation->check_in_date);
        $today = Carbon::today();
        
        // Permitir check-in el mismo día o después de la fecha de reserva
        if ($today->lt($checkInDate)) {
            return response()->json([
                'message' => 'No se puede hacer check-in antes de la fecha de reserva (' . $checkInDate->format('Y-m-d') . ')'
            ], 422);
        }

        // Para pasadías, validar que esté completamente pagada antes del check-in
        if ($reservation->reservation_type === 'day_pass') {
            $totalPrice = $reservation->final_price ?? $reservation->total_price;
            // Calcular total pagado EXCLUYENDO los pagos a crédito del kiosko (que son deudas pendientes, no pagos reales)
            $totalPaid = $reservation->payments()
                ->where(function($query) {
                    $query->where('concept', '!=', 'Compra en kiosko (a crédito)')
                          ->orWhereNull('concept');
                })
                ->sum('amount');
            $remainingBalance = max(0, $totalPrice - $totalPaid);

            if ($remainingBalance > 0 && $reservation->payment_status !== 'free') {
                return response()->json([
                    'message' => 'No se puede hacer check-in de una pasadía que no está completamente pagada. Saldo pendiente: ' . number_format($remainingBalance, 2),
                    'total_price' => $totalPrice,
                    'total_paid' => $totalPaid,
                    'remaining_balance' => $remainingBalance
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Guardar/actualizar huéspedes si se proporcionan (dentro de la transacción)
            if ($request->has('guests') && is_array($request->guests)) {
                foreach ($request->guests as $guestData) {
                    // Validar que tenga los campos mínimos
                    if (empty($guestData['first_name']) || empty($guestData['last_name']) || empty($guestData['document_number'])) {
                        continue; // Saltar huéspedes inválidos
                    }

                    // Verificar si ya existe un huésped con el mismo documento
                    $existingGuest = $reservation->guests()
                        ->where('document_number', $guestData['document_number'])
                        ->where('document_type', $guestData['document_type'] ?? 'CC')
                        ->first();

                    if ($existingGuest) {
                        // Actualizar huésped existente
                        if (isset($guestData['is_primary_guest']) && $guestData['is_primary_guest']) {
                            $reservation->guests()->where('id', '!=', $existingGuest->id)->update(['is_primary_guest' => false]);
                        }
                        $existingGuest->update([
                            'first_name' => $guestData['first_name'],
                            'last_name' => $guestData['last_name'],
                            'document_type' => $guestData['document_type'] ?? 'CC',
                            'document_number' => $guestData['document_number'],
                            'birth_date' => $guestData['birth_date'] ?? null,
                            'gender' => $guestData['gender'] ?? null,
                            'nationality' => $guestData['nationality'] ?? null,
                            'email' => $guestData['email'] ?? null,
                            'phone' => $guestData['phone'] ?? null,
                            'special_needs' => $guestData['special_needs'] ?? null,
                            'is_primary_guest' => $guestData['is_primary_guest'] ?? false,
                            'health_insurance_name' => $guestData['health_insurance_name'] ?? null,
                            'health_insurance_type' => $guestData['health_insurance_type'] ?? null,
                        ]);
                    } else {
                        // Crear nuevo huésped
                        if (isset($guestData['is_primary_guest']) && $guestData['is_primary_guest']) {
                            $reservation->guests()->update(['is_primary_guest' => false]);
                        }
                        $reservation->guests()->create([
                            'first_name' => $guestData['first_name'],
                            'last_name' => $guestData['last_name'],
                            'document_type' => $guestData['document_type'] ?? 'CC',
                            'document_number' => $guestData['document_number'],
                            'birth_date' => $guestData['birth_date'] ?? null,
                            'gender' => $guestData['gender'] ?? null,
                            'nationality' => $guestData['nationality'] ?? null,
                            'email' => $guestData['email'] ?? null,
                            'phone' => $guestData['phone'] ?? null,
                            'special_needs' => $guestData['special_needs'] ?? null,
                            'is_primary_guest' => $guestData['is_primary_guest'] ?? false,
                            'health_insurance_name' => $guestData['health_insurance_name'] ?? null,
                            'health_insurance_type' => $guestData['health_insurance_type'] ?? null,
                        ]);
                    }
                }
            }

            // Actualizar estado y tiempo de check-in (si es reserva múltiple, hacer check-in de todo el grupo)
            $checkInTime = Carbon::now();
            $reservationsToCheckIn = collect([$reservation]);
            if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
                $reservation->load('childReservations');
                $reservationsToCheckIn = $reservationsToCheckIn->merge($reservation->childReservations);
            }
            foreach ($reservationsToCheckIn as $res) {
                $res->update([
                    'status' => 'checked_in',
                    'check_in_time' => $checkInTime,
                ]);
                if ($res->room_id) {
                    $room = $res->relationLoaded('room') ? $res->room : Room::find($res->room_id);
                    if ($room) {
                        $room->update(['status' => 'occupied']);
                    }
                }
            }

            // Registrar inventario inicial del minibar si se proporciona
            if ($reservation->room_id && $request->has('minibar_products')) {
                try {
                    $minibarService = app(\App\Services\MinibarInventoryService::class);
                    $minibarService->recordCheckInInventory(
                        $reservation,
                        $request->minibar_products,
                        auth()->id()
                    );
                } catch (\Exception $e) {
                    \Log::warning('Error registrando inventario del minibar en check-in: ' . $e->getMessage());
                }
            }

            // Registrar auditoría
            $this->auditService->logStatusChange(
                $reservation,
                'confirmed',
                'checked_in',
                'Check-in realizado',
                $request
            );

            // Para pasadías, hacer check-out automático el mismo día
            $autoCheckout = false;
            if ($reservation->reservation_type === 'day_pass') {
                // Hacer check-out automático
                $reservation->update([
                    'status' => 'checked_out',
                    'check_out_time' => $checkInTime, // Mismo tiempo que check-in
                ]);

                // Registrar auditoría del check-out automático
                $this->auditService->logStatusChange(
                    $reservation,
                    'checked_in',
                    'checked_out',
                    'Check-out automático (pasadía)',
                    $request
                );

                // Generar PDF de checkout automático
                $checkoutCertificate = null;
                try {
                    $checkoutCertificate = $this->certificateService->generateCheckoutCertificate($reservation);
                } catch (\Exception $e) {
                    \Log::warning('Error generating checkout certificate for day pass: ' . $e->getMessage());
                }

                // Enviar email con PDF de checkout
                if ($checkoutCertificate) {
                    try {
                        $this->emailService->sendCheckoutConfirmation($reservation, $checkoutCertificate);
                    } catch (\Exception $e) {
                        \Log::warning('Error sending checkout email for day pass: ' . $e->getMessage());
                    }
                }

                $autoCheckout = true;
            }

            // Enviar confirmación de check-in
            try {
                $this->notificationService->sendCheckInConfirmation($reservation);
            } catch (\Exception $e) {
                \Log::warning('Error sending check-in confirmation: ' . $e->getMessage());
            }

            // Actualizar evento en Google Calendar
            if ($reservation->google_calendar_event_id) {
                try {
                    $this->googleCalendarService->updateEvent($reservation);
                } catch (\Exception $e) {
                    \Log::warning('Error updating Google Calendar event: ' . $e->getMessage());
                }
            }

            DB::commit();

            $reservation->load(['customer', 'room', 'room.roomType', 'guests', 'payments']);

            $message = $autoCheckout 
                ? 'Check-in y check-out realizados exitosamente (pasadía)' 
                : 'Check-in realizado exitosamente';

            return response()->json([
                'message' => $message,
                'reservation' => $reservation,
                'auto_checkout' => $autoCheckout
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al realizar el check-in',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Realizar check-out de una reserva
     */
    public function checkOut(Request $request, Reservation $reservation)
    {
        // Las pasadías no pueden hacer check-out manual, se hace automáticamente en el check-in
        if ($reservation->reservation_type === 'day_pass') {
            return response()->json([
                'message' => 'Las pasadías no requieren check-out manual. El check-out se realiza automáticamente al hacer el check-in.'
            ], 422);
        }

        // Validar que la reserva esté en estado checked_in
        if ($reservation->status !== 'checked_in') {
            return response()->json([
                'message' => 'Solo se puede hacer check-out de reservas con check-in realizado. Estado actual: ' . $reservation->status
            ], 422);
        }

        // Validar que la reserva esté completamente pagada (excluyendo pagos a crédito del kiosko)
        // IMPORTANTE: Los cargos del minibar SÍ deben estar pagados para hacer checkout
        $totalPrice = $reservation->final_price ?? $reservation->total_price;
        // Obtener el total de cargos del minibar
        $minibarChargesTotal = $reservation->minibar_charges_total ?? 0;
        
        // Calcular total pagado EXCLUYENDO los pagos a crédito del kiosko (que son deudas pendientes, no pagos reales)
        $totalPaid = $reservation->payments()
            ->where(function($query) {
                $query->where('concept', '!=', 'Compra en kiosko (a crédito)')
                      ->orWhereNull('concept');
            })
            ->sum('amount');
        
        // Calcular cuánto se ha pagado específicamente para cargos del minibar
        $minibarPaid = $reservation->payments()
            ->where(function($query) {
                $query->where('concept', 'like', '%minibar%')
                      ->orWhere('concept', 'like', '%Minibar%');
            })
            ->sum('amount');
        
        // Calcular saldo pendiente de la reserva (incluyendo cargos del minibar)
        $remainingBalance = max(0, $totalPrice - $totalPaid);
        
        // Calcular saldo pendiente específico de cargos del minibar
        $remainingMinibarBalance = max(0, $minibarChargesTotal - $minibarPaid);

        // REGLA 4: Validar que todas las cuentas abiertas (facturas del kiosko con credit = 1) estén pagadas
        // Para reservas múltiples, incluir facturas de todas las reservas del grupo
        
        // Obtener todas las reservas del grupo si es una reserva múltiple
        $groupReservationIds = [];
        if ($reservation->is_group_reservation || $reservation->parent_reservation_id) {
            $allGroupReservations = $reservation->allGroupReservations();
            $groupReservationIds = $allGroupReservations->pluck('id')->toArray();
        } else {
            $groupReservationIds = [$reservation->id];
        }
        
        // Incluir facturas asociadas directamente a la reserva o a cualquier reserva del grupo
        $pendingKioskInvoices = \App\Models\KioskInvoice::whereIn('reservation_id', $groupReservationIds)
            ->whereHas('payment_type', function($query) {
                $query->where('credit', true);
            })
            ->where('payed', false)
            ->with(['details.kiosk_unit.product'])
            ->get();
        
        // También incluir facturas pendientes del cliente que no tienen reservation_id asignado
        // (facturas que deberían estar asociadas a esta reserva o grupo)
        $pendingCustomerInvoices = \App\Models\KioskInvoice::where('customer_id', $reservation->customer_id)
            ->whereHas('payment_type', function($query) {
                $query->where('credit', true);
            })
            ->where('payed', false)
            ->whereNull('reservation_id')
            ->with(['details.kiosk_unit.product'])
            ->get();
        
        // Combinar ambas listas de facturas pendientes
        $pendingKioskInvoices = $pendingKioskInvoices->merge($pendingCustomerInvoices);

        // Calcular total de facturas pendientes del kiosko
        $totalPendingKiosk = $pendingKioskInvoices->sum(function($invoice) {
            return $invoice->details->sum(function($detail) {
                return $detail->price ?? 0;
            });
        });

        // Calcular cuánto se ha pagado adicional a la reserva (para cargos a habitación del kiosko)
        // Primero calcular el precio base sin cargos del minibar para determinar pagos adicionales
        $basePrice = $totalPrice - $minibarChargesTotal;
        $paidForRoomCharges = max(0, $totalPaid - $basePrice - $minibarPaid);
        // El saldo pendiente de cargos a habitación es el total de cargos menos lo que ya se pagó
        $remainingRoomCharges = max(0, $totalPendingKiosk - $paidForRoomCharges);

        // El saldo total pendiente incluye la reserva + cargos del minibar + saldo pendiente de cargos a habitación
        // IMPORTANTE: Los cargos del minibar SÍ bloquean el checkout
        $totalPending = $remainingBalance + $remainingRoomCharges;

        // Si hay saldo pendiente (reserva, cargos del minibar o cargos a habitación del kiosko), bloquear el checkout
        if ($totalPending > 0 && $reservation->payment_status !== 'free') {
            $messageParts = [];
            if ($remainingBalance > 0) {
                // Si hay saldo pendiente de la reserva, desglosar si es por minibar o por reserva base
                if ($remainingMinibarBalance > 0) {
                    $baseRemaining = $remainingBalance - $remainingMinibarBalance;
                    if ($baseRemaining > 0) {
                        $messageParts[] = 'Saldo pendiente de la reserva base: ' . number_format($baseRemaining, 2);
                    }
                    $messageParts[] = 'Saldo pendiente de cargos del minibar: ' . number_format($remainingMinibarBalance, 2);
                } else {
                    $messageParts[] = 'Saldo pendiente de la reserva: ' . number_format($remainingBalance, 2);
                }
            }
            if ($remainingRoomCharges > 0) {
                $messageParts[] = 'Saldo pendiente de cargos a habitación (kiosko): ' . number_format($remainingRoomCharges, 2);
            }
            
            $message = 'No se puede hacer check-out. Tiene saldo pendiente por pagar.';
            if (count($messageParts) > 0) {
                $message .= ' ' . implode('. ', $messageParts) . '.';
            }

            return response()->json([
                'message' => $message,
                'total_price' => $totalPrice,
                'minibar_charges_total' => $minibarChargesTotal,
                'minibar_paid' => $minibarPaid,
                'remaining_minibar_balance' => $remainingMinibarBalance,
                'total_paid' => $totalPaid,
                'reservation_balance' => $remainingBalance,
                'pending_kiosk_invoices' => $totalPendingKiosk,
                'remaining_room_charges' => $remainingRoomCharges,
                'total_pending' => $totalPending,
                'pending_invoices_count' => $pendingKioskInvoices->count(),
                'pending_invoices' => $pendingKioskInvoices->map(function($invoice) {
                    $invoiceTotal = $invoice->details->sum(function($detail) {
                        return $detail->price ?? 0;
                    });
                    return [
                        'id' => $invoice->id,
                        'payment_code' => $invoice->payment_code,
                        'amount' => $invoiceTotal
                    ];
                })
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Actualizar estado y tiempo de check-out (si es reserva múltiple, hacer check-out de todo el grupo)
            $checkOutTime = Carbon::now();
            $reservationsToCheckOut = collect([$reservation]);
            if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
                $reservation->load('childReservations');
                $reservationsToCheckOut = $reservationsToCheckOut->merge($reservation->childReservations);
            }
            foreach ($reservationsToCheckOut as $res) {
                $res->update([
                    'status' => 'checked_out',
                    'check_out_time' => $checkOutTime,
                ]);
                if ($res->room_id) {
                    $room = $res->relationLoaded('room') ? $res->room : Room::find($res->room_id);
                    if ($room) {
                        $room->update(['status' => 'available']);
                    }
                }
            }

            // Registrar inventario final del minibar si se proporciona (solo reserva principal)
            if ($reservation->room_id && $request->has('minibar_products')) {
                try {
                    $minibarService = app(\App\Services\MinibarInventoryService::class);
                    $minibarService->recordInventoryUpdate(
                        $reservation,
                        $request->minibar_products,
                        'check_out',
                        auth()->id()
                    );
                    // Recalcular precio final después de agregar cargos del minibar
                    $reservation->refresh();
                    $reservation->recomputeFinalPrice();
                } catch (\Exception $e) {
                    \Log::warning('Error registrando inventario final del minibar en check-out: ' . $e->getMessage());
                }
            }

            // Registrar auditoría
            $this->auditService->logStatusChange(
                $reservation,
                'checked_in',
                'checked_out',
                'Check-out realizado',
                $request
            );

            // Generar PDF de checkout
            $checkoutCertificate = null;
            try {
                $checkoutCertificate = $this->certificateService->generateCheckoutCertificate($reservation);
            } catch (\Exception $e) {
                \Log::warning('Error generating checkout certificate: ' . $e->getMessage());
            }

            // Generar factura consolidada
            $checkoutInvoice = null;
            try {
                $checkoutInvoice = $this->certificateService->generateCheckoutInvoice($reservation);
            } catch (\Exception $e) {
                \Log::warning('Error generating checkout invoice: ' . $e->getMessage());
            }

            // Enviar email con PDF de checkout y factura consolidada
            if ($checkoutCertificate) {
                try {
                    $this->emailService->sendCheckoutConfirmation($reservation, $checkoutCertificate, $checkoutInvoice);
                } catch (\Exception $e) {
                    \Log::warning('Error sending checkout email: ' . $e->getMessage());
                }
            }

            // Actualizar evento en Google Calendar
            if ($reservation->google_calendar_event_id) {
                try {
                    $this->googleCalendarService->updateEvent($reservation);
                } catch (\Exception $e) {
                    \Log::warning('Error updating Google Calendar event: ' . $e->getMessage());
                }
            }

            DB::commit();

            $reservation->load(['customer', 'room', 'room.roomType', 'guests', 'payments']);

            return response()->json([
                'message' => 'Check-out realizado exitosamente',
                'reservation' => $reservation,
                'certificate' => $checkoutCertificate
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al realizar el check-out',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalcular precio de una reserva
     */
    public function recalculatePrice(Reservation $reservation)
    {
        // Restricción: No se puede recalcular precio de una reserva en estado checked_in o checked_out
        if ($reservation->status === 'checked_in') {
            return response()->json([
                'message' => 'No se puede recalcular el precio de una reserva que ya tiene check-in realizado.'
            ], 403);
        }

        if ($reservation->status === 'checked_out') {
            return response()->json([
                'message' => 'No se puede recalcular el precio de una reserva que ya tiene check-out realizado.'
            ], 403);
        }

        try {
            $priceCalculation = $this->priceCalculator->calculatePrice($reservation, true);

            $reservation->update([
                'calculated_price' => $priceCalculation['calculated_price'],
                'price_breakdown' => $priceCalculation['price_breakdown'],
            ]);
            $reservation->recomputeFinalPrice();

            // Registrar auditoría
            $this->auditService->log('price_recalculated', $reservation, null, [
                'calculated_price' => $priceCalculation['calculated_price'],
            ], 'Precio recalculado', auth()->id(), request());

            return response()->json([
                'message' => 'Precio recalculado exitosamente',
                'calculated_price' => $priceCalculation['calculated_price'],
                'price_breakdown' => $priceCalculation['price_breakdown'],
                'reservation' => $reservation->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recalcular el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de ocupación
     */
    public function occupancyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'room_type_id' => 'nullable|exists:room_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $query = Reservation::where('status', '!=', 'cancelled')
            ->whereBetween('check_in_date', [$request->date_from, $request->date_to]);

        if ($request->room_type_id) {
            $query->where('room_type_id', $request->room_type_id);
        }

        $reservations = $query->with(['room', 'roomType'])->get();

        $occupancyByDate = [];
        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);

        for ($date = $dateFrom->copy(); $date->lte($dateTo); $date->addDay()) {
            $dayReservations = $reservations->filter(function ($reservation) use ($date) {
                return $date->between($reservation->check_in_date, $reservation->check_out_date ?? $reservation->check_in_date);
            });

            $occupancyByDate[] = [
                'date' => $date->format('Y-m-d'),
                'reservations_count' => $dayReservations->count(),
                'rooms_occupied' => $dayReservations->where('reservation_type', 'room')->count(),
                'day_passes' => $dayReservations->where('reservation_type', 'day_pass')->sum(function ($r) {
                    return $r->adults + $r->children;
                }),
            ];
        }

        return response()->json([
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
            'occupancy_by_date' => $occupancyByDate,
            'summary' => [
                'total_reservations' => $reservations->count(),
                'total_room_nights' => $reservations->where('reservation_type', 'room')->sum('nights'),
                'total_day_passes' => $reservations->where('reservation_type', 'day_pass')->count(),
            ],
        ]);
    }

    /**
     * Reporte de ingresos
     */
    public function revenueReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'group_by' => 'nullable|in:day,week,month,reservation_type,room_type',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $query = Reservation::where('status', '!=', 'cancelled')
            ->whereBetween('check_in_date', [$request->date_from, $request->date_to]);

        $reservations = $query->with(['roomType'])->get();

        $groupBy = $request->group_by ?? 'day';

        $revenue = [];
        if ($groupBy === 'day') {
            $grouped = $reservations->groupBy(function ($reservation) {
                return $reservation->check_in_date->format('Y-m-d');
            });
        } elseif ($groupBy === 'week') {
            $grouped = $reservations->groupBy(function ($reservation) {
                return $reservation->check_in_date->format('Y-W');
            });
        } elseif ($groupBy === 'month') {
            $grouped = $reservations->groupBy(function ($reservation) {
                return $reservation->check_in_date->format('Y-m');
            });
        } elseif ($groupBy === 'reservation_type') {
            $grouped = $reservations->groupBy('reservation_type');
        } elseif ($groupBy === 'room_type') {
            $grouped = $reservations->groupBy('room_type_id');
        }

        foreach ($grouped as $key => $group) {
            $revenue[] = [
                'period' => $key,
                'count' => $group->count(),
                'total_revenue' => $group->sum('total_price'),
                'paid_revenue' => $group->where('payment_status', 'paid')->sum('total_price'),
                'pending_revenue' => $group->where('payment_status', 'pending')->sum('total_price'),
                'average_revenue' => $group->avg('total_price'),
            ];
        }

        return response()->json([
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
            'group_by' => $groupBy,
            'revenue' => $revenue,
            'summary' => [
                'total_revenue' => $reservations->sum('total_price'),
                'paid_revenue' => $reservations->where('payment_status', 'paid')->sum('total_price'),
                'pending_revenue' => $reservations->where('payment_status', 'pending')->sum('total_price'),
                'total_reservations' => $reservations->count(),
            ],
        ]);
    }

    /**
     * Reporte de cancelaciones
     */
    public function cancellationsReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $query = Reservation::where('status', 'cancelled');

        if ($request->date_from) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }

        $cancellations = $query->with(['customer', 'roomType'])->get();

        return response()->json([
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
            'cancellations' => $cancellations,
            'summary' => [
                'total_cancellations' => $cancellations->count(),
                'total_lost_revenue' => $cancellations->sum('total_price'),
                'by_reason' => $cancellations->groupBy('cancellation_reason')->map->count(),
            ],
        ]);
    }

    /**
     * Reporte de reservas múltiples (grupos)
     */
    public function groupReservationsReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'room_type_id' => 'nullable|exists:room_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : Carbon::now()->subMonths(3);
        $dateTo = $request->date_to ? Carbon::parse($request->date_to) : Carbon::now();

        // Obtener todas las reservas principales que son grupos
        $query = Reservation::where('is_group_reservation', true)
            ->whereNull('parent_reservation_id') // Solo reservas principales
            ->whereBetween('check_in_date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]);

        if ($request->room_type_id) {
            $query->where('room_type_id', $request->room_type_id);
        }

        $groupReservations = $query->with(['customer', 'roomType', 'childReservations.room', 'childReservations.roomType'])
            ->get();

        // Calcular métricas
        $totalGroups = $groupReservations->count();
        $totalRooms = $groupReservations->sum(function($reservation) {
            return 1 + $reservation->childReservations->count();
        });
        $averageRoomsPerGroup = $totalGroups > 0 ? round($totalRooms / $totalGroups, 2) : 0;
        
        $totalGuests = $groupReservations->sum(function($reservation) {
            $mainGuests = $reservation->adults + $reservation->children + $reservation->infants;
            $childGuests = $reservation->childReservations->sum(function($child) {
                return $child->adults + $child->children + $child->infants;
            });
            return $mainGuests + $childGuests;
        });
        
        $totalRevenue = $groupReservations->sum('total_price');
        $averageRevenuePerGroup = $totalGroups > 0 ? round($totalRevenue / $totalGroups, 2) : 0;

        // Agrupar por tipo de habitación
        $byRoomType = $groupReservations->groupBy('room_type_id')->map(function($reservations, $roomTypeId) {
            $roomType = $reservations->first()->roomType;
            return [
                'room_type_id' => $roomTypeId,
                'room_type_name' => $roomType ? $roomType->name : 'Sin tipo',
                'count' => $reservations->count(),
                'total_rooms' => $reservations->sum(function($r) {
                    return 1 + $r->childReservations->count();
                }),
                'total_revenue' => $reservations->sum('total_price'),
                'average_rooms' => round($reservations->sum(function($r) {
                    return 1 + $r->childReservations->count();
                }) / $reservations->count(), 2),
            ];
        })->values();

        // Distribución por número de habitaciones
        $distributionByRoomCount = [];
        foreach ($groupReservations as $reservation) {
            $roomCount = 1 + $reservation->childReservations->count();
            if (!isset($distributionByRoomCount[$roomCount])) {
                $distributionByRoomCount[$roomCount] = 0;
            }
            $distributionByRoomCount[$roomCount]++;
        }
        ksort($distributionByRoomCount);

        // Estadísticas mensuales
        $monthlyStats = $groupReservations->groupBy(function($reservation) {
            return Carbon::parse($reservation->check_in_date)->format('Y-m');
        })->map(function($reservations, $month) {
            return [
                'month' => $month,
                'count' => $reservations->count(),
                'total_rooms' => $reservations->sum(function($r) {
                    return 1 + $r->childReservations->count();
                }),
                'total_revenue' => $reservations->sum('total_price'),
                'total_guests' => $reservations->sum(function($r) {
                    $main = $r->adults + $r->children + $r->infants;
                    $child = $r->childReservations->sum(function($c) {
                        return $c->adults + $c->children + $c->infants;
                    });
                    return $main + $child;
                }),
            ];
        })->values();

        // Tasa de éxito (reservas confirmadas vs canceladas)
        $confirmed = $groupReservations->where('status', '!=', 'cancelled')->count();
        $cancelled = $groupReservations->where('status', 'cancelled')->count();
        $successRate = $totalGroups > 0 ? round(($confirmed / $totalGroups) * 100, 2) : 0;

        return response()->json([
            'period' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d'),
            ],
            'summary' => [
                'total_groups' => $totalGroups,
                'total_rooms' => $totalRooms,
                'average_rooms_per_group' => $averageRoomsPerGroup,
                'total_guests' => $totalGuests,
                'average_guests_per_group' => $totalGroups > 0 ? round($totalGuests / $totalGroups, 2) : 0,
                'total_revenue' => $totalRevenue,
                'average_revenue_per_group' => $averageRevenuePerGroup,
                'success_rate' => $successRate,
                'confirmed' => $confirmed,
                'cancelled' => $cancelled,
            ],
            'by_room_type' => $byRoomType,
            'distribution_by_room_count' => $distributionByRoomCount,
            'monthly_statistics' => $monthlyStats,
            'reservations' => $groupReservations->map(function($reservation) {
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'check_in_date' => $reservation->check_in_date,
                    'check_out_date' => $reservation->check_out_date,
                    'status' => $reservation->status,
                    'total_rooms' => 1 + $reservation->childReservations->count(),
                    'total_guests' => $reservation->adults + $reservation->children + $reservation->infants + 
                        $reservation->childReservations->sum(function($c) {
                            return $c->adults + $c->children + $c->infants;
                        }),
                    'total_price' => $reservation->total_price,
                    'customer' => $reservation->customer ? [
                        'id' => $reservation->customer->id,
                        'name' => $reservation->customer->name,
                    ] : null,
                    'room_type' => $reservation->roomType ? [
                        'id' => $reservation->roomType->id,
                        'name' => $reservation->roomType->name,
                    ] : null,
                ];
            }),
        ]);
    }

    /**
     * Dashboard del día - Información resumida para un día específico
     */
    public function dailyDashboard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $date = Carbon::parse($request->date)->format('Y-m-d');

        // Reservas que HAN HECHO check-in ese día (solo las que ya tienen check-in realizado)
        $checkIns = Reservation::whereDate('check_in_date', $date)
            ->where('status', 'checked_in')
            ->whereNotNull('check_in_time')
            ->with(['room', 'roomType', 'customer'])
            ->get();

        // Reservas que HAN HECHO check-out ese día (solo las que ya tienen check-out realizado)
        $checkOuts = Reservation::whereDate('check_out_date', $date)
            ->where('status', 'checked_out')
            ->whereNotNull('check_out_time')
            ->with(['room', 'roomType', 'customer'])
            ->get();

        // Reservas activas ese día (check-in <= fecha <= check-out)
        $activeReservations = Reservation::where('check_in_date', '<=', $date)
            ->where('check_out_date', '>=', $date)
            ->where('status', '!=', 'cancelled')
            ->with(['room', 'roomType', 'customer'])
            ->get();

        // Calcular total de personas
        $totalGuests = $activeReservations->sum(function ($reservation) {
            return ($reservation->adults ?? 0) + ($reservation->children ?? 0) + ($reservation->infants ?? 0);
        });

        // Habitaciones que hacen check-in
        $checkInRooms = $checkIns->where('reservation_type', 'room')
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'room' => $reservation->room ? [
                        'id' => $reservation->room->id,
                        'name' => $reservation->room->name,
                    ] : null,
                    'room_type' => $reservation->roomType ? [
                        'id' => $reservation->roomType->id,
                        'name' => $reservation->roomType->name,
                    ] : null,
                    'customer' => $reservation->customer ? [
                        'id' => $reservation->customer->id,
                        'name' => $reservation->customer->name,
                        'email' => $reservation->customer->email,
                    ] : null,
                    'check_in_time' => $reservation->check_in_time,
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                    'infants' => $reservation->infants,
                ];
            })
            ->values();

        // Habitaciones que hacen check-out
        $checkOutRooms = $checkOuts->where('reservation_type', 'room')
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'room' => $reservation->room ? [
                        'id' => $reservation->room->id,
                        'name' => $reservation->room->name,
                    ] : null,
                    'room_type' => $reservation->roomType ? [
                        'id' => $reservation->roomType->id,
                        'name' => $reservation->roomType->name,
                    ] : null,
                    'customer' => $reservation->customer ? [
                        'id' => $reservation->customer->id,
                        'name' => $reservation->customer->name,
                        'email' => $reservation->customer->email,
                    ] : null,
                    'check_out_time' => $reservation->check_out_time,
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                    'infants' => $reservation->infants,
                ];
            })
            ->values();

        // Pasadías ese día
        $dayPasses = $activeReservations->where('reservation_type', 'day_pass')
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'customer' => $reservation->customer ? [
                        'id' => $reservation->customer->id,
                        'name' => $reservation->customer->name,
                        'email' => $reservation->customer->email,
                    ] : null,
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                    'infants' => $reservation->infants,
                ];
            })
            ->values();

        return response()->json([
            'date' => $date,
            'summary' => [
                'total_reservations' => $activeReservations->count(),
                'total_guests' => $totalGuests,
                'check_ins_count' => $checkIns->count(),
                'check_outs_count' => $checkOuts->count(),
                'day_passes_count' => $dayPasses->count(),
            ],
            'check_ins' => $checkInRooms,
            'check_outs' => $checkOutRooms,
            'day_passes' => $dayPasses,
            'active_reservations' => $activeReservations->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'reservation_type' => $reservation->reservation_type,
                    'room' => $reservation->room ? [
                        'id' => $reservation->room->id,
                        'name' => $reservation->room->name,
                    ] : null,
                    'room_type' => $reservation->roomType ? [
                        'id' => $reservation->roomType->id,
                        'name' => $reservation->roomType->name,
                    ] : null,
                    'customer' => $reservation->customer ? [
                        'id' => $reservation->customer->id,
                        'name' => $reservation->customer->name,
                    ] : null,
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                    'infants' => $reservation->infants,
                ];
            })->values(),
        ]);
    }

    /**
     * Programa limpiezas automáticas para una reserva
     * Crea limpiezas para check-in y check-out
     */
    private function scheduleCleaningForReservation(Reservation $reservation)
    {
        // Solo programar si la reserva tiene habitación asignada
        if (!$reservation->room_id || !$reservation->check_in_date || !$reservation->check_out_date) {
            return;
        }

        // Limpieza de check-in (día de entrada)
        CleaningRecord::create([
            'cleanable_type' => Room::class,
            'cleanable_id' => $reservation->room_id,
            'reservation_id' => $reservation->id,
            'cleaning_date' => $reservation->check_in_date,
            'cleaning_type' => 'checkin',
            'status' => 'pending',
            'cleaned_by' => null, // Se asignará cuando se complete
        ]);

        // Limpieza de check-out (día de salida)
        CleaningRecord::create([
            'cleanable_type' => Room::class,
            'cleanable_id' => $reservation->room_id,
            'reservation_id' => $reservation->id,
            'cleaning_date' => $reservation->check_out_date,
            'cleaning_type' => 'checkout',
            'status' => 'pending',
            'cleaned_by' => null, // Se asignará cuando se complete
        ]);
    }

    /**
     * Actualiza limpiezas programadas cuando cambian las fechas de una reserva
     */
    /**
     * Distribuye huéspedes de manera inteligente manteniendo familias juntas
     */
    private function distributeGuestsIntelligently($availableRooms, $totalGuests, $adults, $children, $infants, $guestsData = [])
    {
        $roomsNeeded = [];
        $remainingGuests = $totalGuests;
        $remainingAdults = $adults;
        $remainingChildren = $children;
        $remainingInfants = $infants;

        // Si hay información detallada de huéspedes, agrupar por familias
        $familyGroups = [];
        if (!empty($guestsData) && count($guestsData) > 0) {
            // Agrupar por apellido (familias)
            $familiesByLastName = [];
            foreach ($guestsData as $guest) {
                $lastName = strtolower(trim($guest['last_name'] ?? ''));
                if ($lastName) {
                    if (!isset($familiesByLastName[$lastName])) {
                        $familiesByLastName[$lastName] = [];
                    }
                    $familiesByLastName[$lastName][] = $guest;
                }
            }

            // Calcular edad de cada huésped para clasificar
            $now = now();
            foreach ($familiesByLastName as $lastName => $familyMembers) {
                $familyAdults = 0;
                $familyChildren = 0;
                $familyInfants = 0;
                $familyMembersWithAge = [];

                foreach ($familyMembers as $member) {
                    $age = null;
                    if (isset($member['birth_date']) && $member['birth_date']) {
                        try {
                            $birthDate = \Carbon\Carbon::parse($member['birth_date']);
                            $age = $now->diffInYears($birthDate);
                        } catch (\Exception $e) {
                            // Si no se puede calcular la edad, asumir adulto
                            $age = 18;
                        }
                    }

                    // Clasificar: bebé (0-2), niño (3-12), adulto (13+)
                    if ($age === null || $age >= 13) {
                        $familyAdults++;
                        $member['calculated_age'] = $age ?? 18;
                        $member['guest_type'] = 'adult';
                    } elseif ($age >= 3) {
                        $familyChildren++;
                        $member['calculated_age'] = $age;
                        $member['guest_type'] = 'child';
                    } else {
                        $familyInfants++;
                        $member['calculated_age'] = $age;
                        $member['guest_type'] = 'infant';
                    }

                    $familyMembersWithAge[] = $member;
                }

                if ($familyAdults > 0 || $familyChildren > 0 || $familyInfants > 0) {
                    $familyGroups[] = [
                        'last_name' => $lastName,
                        'adults' => $familyAdults,
                        'children' => $familyChildren,
                        'infants' => $familyInfants,
                        'total' => $familyAdults + $familyChildren + $familyInfants,
                        'members' => $familyMembersWithAge,
                    ];
                }
            }

            // Ordenar familias por tamaño (más grandes primero) para asignarlas primero
            usort($familyGroups, function($a, $b) {
                return $b['total'] <=> $a['total'];
            });
        }

        // Si no hay información de huéspedes o no se pudieron agrupar familias,
        // usar distribución simple pero mejorada
        $getRoomCapacity = fn ($room) => (int) ($room->max_capacity ?? $room->capacity);

        if (empty($familyGroups)) {
            \Log::info('No hay información de huéspedes para agrupar familias, usando distribución simple mejorada');
            
            foreach ($availableRooms as $room) {
                if ($remainingGuests <= 0) {
                    break;
                }

                $roomCapacity = $getRoomCapacity($room);
                $guestsForThisRoom = min($remainingGuests, $roomCapacity);

                // Intentar mantener proporción de adultos/niños/bebés
                $adultsForRoom = min($remainingAdults, $guestsForThisRoom);
                $remainingAdults -= $adultsForRoom;
                $guestsForThisRoom -= $adultsForRoom;

                $childrenForRoom = min($remainingChildren, $guestsForThisRoom);
                $remainingChildren -= $childrenForRoom;
                $guestsForThisRoom -= $childrenForRoom;

                $infantsForRoom = min($remainingInfants, $guestsForThisRoom);
                $remainingInfants -= $infantsForRoom;

                $roomsNeeded[] = [
                    'room' => $room,
                    'guests_count' => $adultsForRoom + $childrenForRoom + $infantsForRoom,
                    'adults' => $adultsForRoom,
                    'children' => $childrenForRoom,
                    'infants' => $infantsForRoom,
                ];

                $remainingGuests -= ($adultsForRoom + $childrenForRoom + $infantsForRoom);
            }
        } else {
            // Distribución inteligente manteniendo familias juntas
            \Log::info('Distribuyendo huéspedes por familias', [
                'families_count' => count($familyGroups),
                'families' => array_map(function($f) {
                    return ['last_name' => $f['last_name'], 'total' => $f['total']];
                }, $familyGroups),
            ]);

            $roomIndex = 0;
            foreach ($familyGroups as $family) {
                // Buscar habitación que pueda alojar a toda la familia
                $familyPlaced = false;
                
                // Primero intentar colocar la familia completa en una habitación
                for ($i = $roomIndex; $i < $availableRooms->count(); $i++) {
                    $room = $availableRooms->values()[$i];
                    if ($getRoomCapacity($room) >= $family['total']) {
                        // Esta habitación puede alojar a toda la familia
                        $roomsNeeded[] = [
                            'room' => $room,
                            'guests_count' => $family['total'],
                            'adults' => $family['adults'],
                            'children' => $family['children'],
                            'infants' => $family['infants'],
                            'family_last_name' => $family['last_name'],
                        ];
                        $remainingGuests -= $family['total'];
                        $remainingAdults -= $family['adults'];
                        $remainingChildren -= $family['children'];
                        $remainingInfants -= $family['infants'];
                        $roomIndex = $i + 1;
                        $familyPlaced = true;
                        break;
                    }
                }

                // Si no se pudo colocar la familia completa, dividirla
                if (!$familyPlaced) {
                    // Dividir la familia en múltiples habitaciones
                    $familyAdultsRemaining = $family['adults'];
                    $familyChildrenRemaining = $family['children'];
                    $familyInfantsRemaining = $family['infants'];

                    for ($i = $roomIndex; $i < $availableRooms->count() && ($familyAdultsRemaining > 0 || $familyChildrenRemaining > 0 || $familyInfantsRemaining > 0); $i++) {
                        $room = $availableRooms->values()[$i];
                        $roomCapacity = $getRoomCapacity($room);
                        $roomGuests = 0;
                        $roomAdults = 0;
                        $roomChildren = 0;
                        $roomInfants = 0;

                        // Priorizar: adultos con niños, luego bebés
                        $roomAdults = min($familyAdultsRemaining, $roomCapacity);
                        $familyAdultsRemaining -= $roomAdults;
                        $roomGuests += $roomAdults;
                        $roomCapacity -= $roomAdults;

                        $roomChildren = min($familyChildrenRemaining, $roomCapacity);
                        $familyChildrenRemaining -= $roomChildren;
                        $roomGuests += $roomChildren;
                        $roomCapacity -= $roomChildren;

                        $roomInfants = min($familyInfantsRemaining, $roomCapacity);
                        $familyInfantsRemaining -= $roomInfants;
                        $roomGuests += $roomInfants;

                        if ($roomGuests > 0) {
                            $roomsNeeded[] = [
                                'room' => $room,
                                'guests_count' => $roomGuests,
                                'adults' => $roomAdults,
                                'children' => $roomChildren,
                                'infants' => $roomInfants,
                                'family_last_name' => $family['last_name'],
                            ];
                            $remainingGuests -= $roomGuests;
                            $remainingAdults -= $roomAdults;
                            $remainingChildren -= $roomChildren;
                            $remainingInfants -= $roomInfants;
                        }
                    }

                    $roomIndex = $i;
                }
            }

            // Si quedan huéspedes sin asignar (por ejemplo, si no había información de huéspedes completa),
            // usar distribución simple para los restantes
            if ($remainingGuests > 0) {
                \Log::info('Quedan huéspedes sin asignar después de distribución por familias, usando distribución simple', [
                    'remaining_guests' => $remainingGuests,
                ]);

                for ($i = $roomIndex; $i < $availableRooms->count() && $remainingGuests > 0; $i++) {
                    $room = $availableRooms->values()[$i];
                    $roomCapacity = $getRoomCapacity($room);
                    $guestsForThisRoom = min($remainingGuests, $roomCapacity);

                    $adultsForRoom = min($remainingAdults, $guestsForThisRoom);
                    $remainingAdults -= $adultsForRoom;
                    $guestsForThisRoom -= $adultsForRoom;

                    $childrenForRoom = min($remainingChildren, $guestsForThisRoom);
                    $remainingChildren -= $childrenForRoom;
                    $guestsForThisRoom -= $childrenForRoom;

                    $infantsForRoom = min($remainingInfants, $guestsForThisRoom);
                    $remainingInfants -= $infantsForRoom;

                    $roomsNeeded[] = [
                        'room' => $room,
                        'guests_count' => $adultsForRoom + $childrenForRoom + $infantsForRoom,
                        'adults' => $adultsForRoom,
                        'children' => $childrenForRoom,
                        'infants' => $infantsForRoom,
                    ];

                    $remainingGuests -= ($adultsForRoom + $childrenForRoom + $infantsForRoom);
                }
            }
        }

        // Validar que todos los huéspedes fueron asignados
        if ($remainingGuests > 0) {
            \Log::warning('No se pudieron asignar todos los huéspedes después de distribución inteligente', [
                'remaining_guests' => $remainingGuests,
                'remaining_adults' => $remainingAdults,
                'remaining_children' => $remainingChildren,
                'remaining_infants' => $remainingInfants,
            ]);
        }

        \Log::info('Distribución inteligente completada', [
            'rooms_assigned' => count($roomsNeeded),
            'total_guests_distributed' => $totalGuests - $remainingGuests,
            'families_kept_together' => !empty($familyGroups) ? count($familyGroups) : 0,
        ]);

        return $roomsNeeded;
    }

    private function updateCleaningForReservation(Reservation $reservation)
    {
        // Obtener limpiezas pendientes relacionadas con esta reserva
        $pendingCleanings = CleaningRecord::where('reservation_id', $reservation->id)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingCleanings as $cleaning) {
            // Actualizar fecha según el tipo de limpieza
            if ($cleaning->cleaning_type === 'checkin' && $reservation->check_in_date) {
                $cleaning->update(['cleaning_date' => $reservation->check_in_date]);
            } elseif ($cleaning->cleaning_type === 'checkout' && $reservation->check_out_date) {
                $cleaning->update(['cleaning_date' => $reservation->check_out_date]);
            }
        }
    }
}

