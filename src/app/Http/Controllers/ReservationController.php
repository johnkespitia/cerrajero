<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ReservationPayment;
use App\Models\ReservationSetting;
use App\Services\GoogleCalendarService;
use App\Services\ReservationCertificateService;
use App\Services\ReservationEmailService;
use App\Services\ReservationPriceCalculator;
use App\Services\ReservationAuditService;
use App\Services\ReservationValidationService;
use App\Services\ReservationNotificationService;
use App\Services\ReservationCancellationService;
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

    public function __construct(
        GoogleCalendarService $googleCalendarService,
        ReservationCertificateService $certificateService,
        ReservationEmailService $emailService,
        ReservationPriceCalculator $priceCalculator,
        ReservationAuditService $auditService,
        ReservationValidationService $validationService,
        ReservationNotificationService $notificationService,
        ReservationCancellationService $cancellationService
    ) {
        $this->googleCalendarService = $googleCalendarService;
        $this->certificateService = $certificateService;
        $this->emailService = $emailService;
        $this->priceCalculator = $priceCalculator;
        $this->auditService = $auditService;
        $this->validationService = $validationService;
        $this->notificationService = $notificationService;
        $this->cancellationService = $cancellationService;
    }

    public function index(Request $request)
    {
        $with = [
            'customer',
            'room',
            'room.roomType',
            'roomType',
            'guests',
            'childReservations' => function($query) {
                $query->with(['room', 'room.roomType', 'customer']);
            },
            'parentReservation' => function($query) {
                $query->with(['room', 'room.roomType', 'customer']);
            },
            'cancellationPolicy'
        ];

        // Incluir pagos si se solicita
        if ($request->has('include') && str_contains($request->include, 'payments')) {
            $with[] = 'payments';
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
                          $request->has('customer_document');
        
        // Si no hay filtros activos, limitar a últimos 30 días por defecto
        if (!$hasDateFilter && !$hasOtherFilters && !$request->boolean('show_all')) {
            $thirtyDaysAgo = now()->subDays(30)->format('Y-m-d');
            $query->where('check_in_date', '>=', $thirtyDaysAgo);
        }

        // Soporte para paginación
        if ($request->has('per_page') || $request->has('page')) {
            $perPage = $request->input('per_page', 50);
            $reservations = $query->orderBy('check_in_date', 'desc')->paginate($perPage);
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

        return response()->json(
            $query->orderBy('check_in_date', 'desc')->get()
        );
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'customer',
            'room',
            'room.roomType',
            'roomType',
            'guests',
            'createdBy',
            'childReservations',
            'parentReservation',
            'payments',
            'audits.user',
            'promotion',
            'cancellationPolicy'
        ]);

        return response()->json($reservation);
    }

    /**
     * Crear reserva con múltiples habitaciones cuando se supera el aforo
     */
    protected function createMultiRoomReservation(Request $request, $roomTypeId, $totalGuests)
    {
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
            return response()->json([
                'message' => 'No hay suficientes habitaciones disponibles para alojar a todos los huéspedes'
            ], 409);
        }

        $roomsNeeded = [];
        $remainingGuests = $totalGuests;
        $guests = $request->has('guests') && is_array($request->guests) ? $request->guests : [];

        foreach ($availableRooms as $room) {
            if ($remainingGuests <= 0) {
                break;
            }

            $guestsForThisRoom = min($remainingGuests, $room->capacity);
            $roomsNeeded[] = [
                'room' => $room,
                'guests_count' => $guestsForThisRoom,
                'adults' => min($request->adults, $guestsForThisRoom),
                'children' => min($request->children ?? 0, max(0, $guestsForThisRoom - $request->adults)),
                'infants' => min(
                    $request->infants ?? 0,
                    max(0, $guestsForThisRoom - $request->adults - ($request->children ?? 0))
                )
            ];

            $remainingGuests -= $guestsForThisRoom;
        }

        if ($remainingGuests > 0) {
            return response()->json([
                'message' => 'No hay suficientes habitaciones disponibles. Faltan ' . $remainingGuests . ' espacios'
            ], 409);
        }

        // Reserva principal
        $mainRoom = $roomsNeeded[0];
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

        if ($request->has('total_price')) {
            $mainReservation->total_price = $request->total_price;
        } else {
            $mainReservation->total_price = $totalPrice;
        }
        $mainReservation->save();

        // Google Calendar
        try {
            $this->googleCalendarService->createEvent($mainReservation);
            foreach ($childReservations as $child) {
                $this->googleCalendarService->createEvent($child);
            }
        } catch (\Exception $e) {
            \Log::warning('Error creating Google Calendar events: ' . $e->getMessage());
        }

        // Email (solo reserva principal)
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
            'childReservations'
        ]);

        return response()->json([
            'message' => 'Reserva creada con múltiples habitaciones',
            'main_reservation' => $mainReservation,
            'child_reservations' => $childReservations,
            'total_rooms' => count($roomsNeeded),
            'total_price' => $totalPrice
        ], 201);
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

            $reservation->load(['customer', 'room', 'room.roomType', 'guests']);

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
                
                // Calcular total pagado
                $totalPaid = $reservation->payments()->sum('amount');
                
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

            DB::commit();

            $reservation->load(['customer', 'room', 'room.roomType']);

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

        // Lógica para habitaciones (código existente)
        $query = Room::where('status', 'available')
            ->where('active', true)
            ->where('capacity', '>=', $totalGuests);

        if ($request->room_type_id) {
            $query->where('room_type_id', $request->room_type_id);
        }

        $availableRooms = $query->with('roomType')->get()->filter(function ($room) use ($checkIn, $checkOut, $totalGuests) {
            return $room->isAvailable($checkIn, $checkOut) && $room->canAccommodate($totalGuests, 0, 0);
        });

        $roomsByType = $availableRooms->groupBy('room_type_id')->map(function ($rooms) {
            $roomType = $rooms->first()->roomType;
            return [
                'room_type' => $roomType,
                'rooms' => $rooms->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'number' => $room->number,
                        'name' => $room->name,
                        'display_name' => $room->display_name,
                        'capacity' => $room->capacity,
                        'room_price' => $room->room_price,
                        'description' => $room->description,
                    ];
                }),
                'count' => $rooms->count(),
                'min_price' => $rooms->min('room_price'),
                'max_price' => $rooms->max('room_price'),
            ];
        });

        return response()->json([
            'reservation_type' => 'room',
            'available_rooms' => $availableRooms->load('roomType')->values()->toArray(),
            'rooms_by_type' => $roomsByType,
            'count' => $availableRooms->count(),
            'total_guests' => $totalGuests,
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
        ]);
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
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'concept' => 'nullable|string|max:200',
            'payment_method' => 'required|in:cash,card,transfer,check,other',
            'payment_reference' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Calcular total ya pagado
            $totalPaid = $reservation->payments()->sum('amount');
            $finalPrice = $reservation->final_price ?? $reservation->total_price;
            $remainingBalance = max(0, $finalPrice - $totalPaid);

            // Validar que el pago no exceda el saldo pendiente
            if ($request->amount > $remainingBalance) {
                return response()->json([
                    'message' => "El monto del pago ({$request->amount}) excede el saldo pendiente ({$remainingBalance}). El total de la reserva es {$finalPrice} y ya se han pagado {$totalPaid}.",
                    'total_price' => $finalPrice,
                    'total_paid' => $totalPaid,
                    'remaining_balance' => $remainingBalance,
                    'payment_amount' => $request->amount
                ], 422);
            }

            $payment = ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $request->amount,
                'concept' => $request->concept,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Actualizar estado de pago de la reserva
            $newTotalPaid = $reservation->payments()->sum('amount');

            if ($newTotalPaid >= $finalPrice) {
                $reservation->payment_status = 'paid';
            } elseif ($newTotalPaid > 0) {
                $reservation->payment_status = 'partial';
            }

            $reservation->save();

            // Registrar auditoría
            $this->auditService->logPayment($reservation, $request->amount, $request->payment_method, $request->notes, $request);

            DB::commit();

            return response()->json([
                'message' => 'Pago registrado exitosamente',
                'payment' => $payment,
                'reservation' => $reservation->fresh(['payments', 'customer']),
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
            $totalPaid = $reservation->payments()->sum('amount');
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

            // Actualizar estado y tiempo de check-in
            $checkInTime = Carbon::now();
            $reservation->update([
                'status' => 'checked_in',
                'check_in_time' => $checkInTime,
            ]);

            // Si tiene habitación asignada, cambiar su estado a occupied
            if ($reservation->room_id) {
                $reservation->room->update(['status' => 'occupied']);
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

        // Validar que la reserva esté completamente pagada
        $totalPrice = $reservation->final_price ?? $reservation->total_price;
        $totalPaid = $reservation->payments()->sum('amount');
        $remainingBalance = max(0, $totalPrice - $totalPaid);

        if ($remainingBalance > 0 && $reservation->payment_status !== 'free') {
            return response()->json([
                'message' => 'No se puede hacer check-out de una reserva que no está completamente pagada. Saldo pendiente: ' . number_format($remainingBalance, 2),
                'total_price' => $totalPrice,
                'total_paid' => $totalPaid,
                'remaining_balance' => $remainingBalance
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Actualizar estado y tiempo de check-out
            $reservation->update([
                'status' => 'checked_out',
                'check_out_time' => Carbon::now(),
            ]);

            // Si tiene habitación asignada, cambiar su estado a available
            if ($reservation->room_id) {
                // Cargar la relación si no está cargada
                if (!$reservation->relationLoaded('room')) {
                    $reservation->load('room');
                }
                
                // Actualizar el estado de la habitación
                $room = Room::find($reservation->room_id);
                if ($room) {
                    $room->update(['status' => 'available']);
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

            // Enviar email con PDF de checkout
            if ($checkoutCertificate) {
                try {
                    $this->emailService->sendCheckoutConfirmation($reservation, $checkoutCertificate);
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
                'final_price' => $priceCalculation['calculated_price'] - ($reservation->discount_amount ?? 0),
            ]);

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
}

