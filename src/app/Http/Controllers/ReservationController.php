<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\GoogleCalendarService;
use App\Services\ReservationCertificateService;
use App\Services\ReservationEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReservationController extends Controller
{
    protected $googleCalendarService;
    protected $certificateService;
    protected $emailService;

    public function __construct(
        GoogleCalendarService $googleCalendarService,
        ReservationCertificateService $certificateService,
        ReservationEmailService $emailService
    ) {
        $this->googleCalendarService = $googleCalendarService;
        $this->certificateService = $certificateService;
        $this->emailService = $emailService;
    }

    public function index(Request $request)
    {
        $query = Reservation::with([
            'customer',
            'room',
            'room.roomType',
            'roomType',
            'guests',
            'childReservations',
            'parentReservation'
        ]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
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
            'parentReservation'
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

            // Calcular total automáticamente si no se envía
            $chargeableGuests = $request->adults + ($request->children ?? 0);
            $computedTotal = 0;
            
            if ($request->reservation_type === 'day_pass') {
                // Para pasadía, calcular según precios del día
                $dayPassCapacity = \App\Models\DayPassCapacity::getOrCreateForDate($request->check_in_date, 0, 0, 0);
                $adults = $request->adults ?? 1;
                $children = $request->children ?? 0;
                $computedTotal = $dayPassCapacity->calculatePrice($adults, $children);
            } else {
                // Para habitaciones: total = (adultos + niños) * precio_por_persona_por_noche * noches
                $nights = 1;
                if ($request->check_out_date) {
                    $nights = Carbon::parse($request->check_in_date)
                        ->diffInDays(Carbon::parse($request->check_out_date)) ?: 1;
                }

                $perPersonPrice = 0;
                if ($request->room_id) {
                    $roomForPrice = isset($room) ? $room : Room::find($request->room_id);
                    $perPersonPrice = $roomForPrice ? $roomForPrice->room_price : 0;
                } elseif ($request->room_type_id) {
                    $roomTypeForPrice = RoomType::find($request->room_type_id);
                    $perPersonPrice = $roomTypeForPrice && $roomTypeForPrice->base_price !== null
                        ? $roomTypeForPrice->base_price
                        : 0;
                }

                $computedTotal = $perPersonPrice * $chargeableGuests * $nights;
            }
            
            $totalPrice = $request->total_price !== null ? $request->total_price : $computedTotal;

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
                'total_price' => $totalPrice,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'special_requests' => $request->special_requests,
                'status' => 'confirmed',
                'payment_status' => $request->payment_status ?? 'pending',
                'free_reservation_reason' => $request->free_reservation_reason,
                'free_reservation_reference' => $request->free_reservation_reference,
                'created_by' => $request->user()->id ?? null,
                'contact_channel' => $request->contact_channel,
                'referral_source' => $request->referral_source,
                'social_media_platform' => $request->social_media_platform,
                'campaign_name' => $request->campaign_name,
                'tracking_code' => $request->tracking_code,
                'marketing_notes' => $request->marketing_notes,
            ]);

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

            $reservation->update($request->only([
                'room_id',
                'check_in_date',
                'check_out_date',
                'adults',
                'children',
                'infants',
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
                'marketing_notes'
            ]));

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
            'available_rooms' => $availableRooms->load('roomType'),
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
}

