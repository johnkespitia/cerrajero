<?php

namespace App\Services;

use App\Http\Controllers\ReservationController;
use App\Models\AdditionalService;
use App\Models\Customer;
use App\Models\DayPassCapacity;
use App\Models\Reservation;
use App\Models\ReservationSetting;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServicePackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PublicBookingService
{
    public const PAYMENT_MODES = ['request_only', 'deposit', 'full_payment'];

    public const PAYMENT_MODES_REQUIRING_RECEIPT = ['deposit', 'full_payment'];

    public function __construct(
        protected ReservationController $reservationController
    ) {
    }

    public function getConfig(): array
    {
        $defaults = [
            'web_booking_enabled' => 'true',
            'web_payment_mode_request_enabled' => 'true',
            'web_payment_mode_deposit_enabled' => 'true',
            'web_payment_mode_full_enabled' => 'true',
            'web_deposit_percentage' => '30',
            'web_payment_instructions' => 'Realice la transferencia o consignación y adjunte el comprobante (captura o PDF). Verificaremos el pago para confirmar su reserva.',
        ];

        foreach ($defaults as $key => $value) {
            if (ReservationSetting::get($key) === null) {
                ReservationSetting::set($key, $value);
            }
        }

        return [
            'enabled' => filter_var(ReservationSetting::get('web_booking_enabled', 'true'), FILTER_VALIDATE_BOOLEAN),
            'max_advance_days' => ReservationSetting::getInt('max_advance_days', 365),
            'min_stay_nights' => ReservationSetting::getInt('min_stay_nights', 1),
            'max_stay_nights' => ReservationSetting::getInt('max_stay_nights', 30),
            'check_in_time' => ReservationSetting::get('check_in_time', '15:00'),
            'check_out_time' => ReservationSetting::get('check_out_time', '12:00'),
            'payment_modes' => [
                'request_only' => filter_var(
                    ReservationSetting::get('web_payment_mode_request_enabled', 'true'),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'deposit' => filter_var(
                    ReservationSetting::get('web_payment_mode_deposit_enabled', 'true'),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'full_payment' => filter_var(
                    ReservationSetting::get('web_payment_mode_full_enabled', 'true'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ],
            'deposit_percentage' => ReservationSetting::getFloat('web_deposit_percentage', 30),
            'payment_instructions' => ReservationSetting::get(
                'web_payment_instructions',
                'Realice la transferencia o consignación y adjunte el comprobante (captura o PDF). Verificaremos el pago para confirmar su reserva.'
            ),
        ];
    }

    public function requiresPaymentReceipt(string $paymentMode): bool
    {
        return in_array($paymentMode, self::PAYMENT_MODES_REQUIRING_RECEIPT, true);
    }

    public function getRoomTypes(): array
    {
        return RoomType::where('active', true)
            ->orderBy('name')
            ->get($this->roomTypeSelectColumns())
            ->map(fn (RoomType $roomType) => $this->formatRoomTypeForPublic($roomType))
            ->values()
            ->all();
    }

    protected function roomTypeSelectColumns(): array
    {
        $columns = [
            'id',
            'name',
            'code',
            'description',
            'default_capacity',
            'max_capacity',
            'base_price',
            'features',
        ];

        if (Schema::hasColumn('room_types', 'image_url')) {
            $columns[] = 'image_url';
        }

        if (Schema::hasColumn('room_types', 'gallery')) {
            $columns[] = 'gallery';
        }

        return $columns;
    }

    protected function formatRoomTypeForPublic(RoomType $roomType): array
    {
        $data = $roomType->toArray();
        $data['image_url'] = $data['image_url'] ?? null;
        $data['gallery'] = $data['gallery'] ?? [];

        return $data;
    }

    public function getPlans(): array
    {
        $config = $this->getConfig();
        $today = Carbon::today()->format('Y-m-d');

        $dayPassCapacity = DayPassCapacity::where('date', '>=', $today)
            ->orderBy('date')
            ->first()
            ?? DayPassCapacity::getOrCreateForDate($today);

        $packages = ServicePackage::query()
            ->active()
            ->with([
                'roomType' => fn ($query) => $query->select($this->roomTypeSelectColumns()),
                'additionalServices' => fn ($query) => $query->active()->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (ServicePackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'room_type' => $package->roomType ? $this->formatRoomTypeForPublic($package->roomType) : null,
                'services' => $package->additionalServices
                    ->map(fn (AdditionalService $service) => $this->formatPlanService($service))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $dayPassServices = AdditionalService::query()
            ->active()
            ->forReservationType('day_pass')
            ->orderBy('name')
            ->get()
            ->map(fn (AdditionalService $service) => $this->formatPlanService($service))
            ->values()
            ->all();

        $lodgingExtras = AdditionalService::query()
            ->active()
            ->forReservationType('room')
            ->orderBy('name')
            ->get()
            ->map(fn (AdditionalService $service) => $this->formatPlanService($service))
            ->values()
            ->all();

        return [
            'lodging' => [
                'room_types' => $this->getRoomTypes(),
                'packages' => $packages,
                'additional_services' => $lodgingExtras,
            ],
            'day_pass' => [
                'adult_price' => (float) $dayPassCapacity->adult_price,
                'child_price' => (float) $dayPassCapacity->child_price,
                'max_capacity' => (int) $dayPassCapacity->max_capacity,
                'description' => ReservationSetting::get(
                    'day_pass_public_description',
                    'Disfrute un día completo en nuestras instalaciones: piscina, zonas verdes, restaurante y actividades al aire libre.'
                ),
                'services' => $dayPassServices,
            ],
            'config' => [
                'check_in_time' => $config['check_in_time'],
                'check_out_time' => $config['check_out_time'],
            ],
        ];
    }

    protected function formatPlanService(AdditionalService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'price' => (float) $service->price,
            'billing_type' => $service->billing_type,
            'is_per_guest' => (bool) $service->is_per_guest,
            'is_food_service' => (bool) $service->is_food_service,
            'applies_to' => $service->applies_to,
        ];
    }

    public function checkAvailability(Request $request): Response
    {
        return $this->reservationController->checkAvailability($request);
    }

    public function getMonthlyCalendar(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'reservation_type' => 'required|in:room,day_pass',
            'adults' => 'integer|min:1',
            'children' => 'integer|min:0',
            'room_type_id' => 'nullable|exists:room_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $year = (int) $request->year;
        $month = (int) $request->month;
        $reservationType = $request->reservation_type;
        $adults = (int) ($request->adults ?? 2);
        $children = (int) ($request->children ?? 0);
        $guests = $adults + $children;

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();
        $minNights = ReservationSetting::getInt('min_stay_nights', 1);
        $maxAdvanceDays = ReservationSetting::getInt('max_advance_days', 365);
        $maxBookableDate = $today->copy()->addDays($maxAdvanceDays);

        $days = [];

        if ($reservationType === 'day_pass') {
            $capacities = DayPassCapacity::whereBetween('date', [
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ])->get()->keyBy(fn ($c) => $c->date->format('Y-m-d'));

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateStr = $date->format('Y-m-d');
                $capacity = $capacities->get($dateStr)
                    ?? DayPassCapacity::getOrCreateForDate($dateStr, 0, 0, 0);

                $days[] = $this->formatCalendarDay(
                    $date,
                    $today,
                    $maxBookableDate,
                    $capacity->available_capacity,
                    $capacity->max_capacity,
                    $guests
                );
            }
        } else {
            $roomsQuery = Room::where('active', true);
            if ($request->room_type_id) {
                $roomsQuery->where('room_type_id', (int) $request->room_type_id);
            }
            $allRooms = $roomsQuery->get();
            $totalRooms = $allRooms->count();

            $getEffectiveCapacity = fn (Room $room) => (int) ($room->max_capacity ?? $room->capacity);

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $checkIn = $date->format('Y-m-d');
                $checkOut = $date->copy()->addDays($minNights)->format('Y-m-d');

                $availableRooms = $allRooms->filter(
                    fn (Room $room) => $room->isAvailable($checkIn, $checkOut)
                );

                $singleRoomOptions = $availableRooms->filter(
                    fn (Room $room) => $room->canAccommodate($adults, $children, 0)
                );

                $totalCapacity = $availableRooms->sum(
                    fn (Room $room) => $getEffectiveCapacity($room)
                );

                $multiRoomRequired = false;
                $availableCount = 0;

                if ($singleRoomOptions->isNotEmpty()) {
                    $availableCount = $singleRoomOptions->count();
                } elseif ($availableRooms->isNotEmpty() && $totalCapacity >= $guests) {
                    $availableCount = $availableRooms->count();
                    $multiRoomRequired = true;
                }

                $days[] = $this->formatCalendarDay(
                    $date,
                    $today,
                    $maxBookableDate,
                    $availableCount,
                    $totalRooms,
                    $multiRoomRequired ? 1 : $guests,
                    $multiRoomRequired
                );
            }
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'reservation_type' => $reservationType,
            'room_type_id' => $request->room_type_id ? (int) $request->room_type_id : null,
            'guests' => $guests,
            'days' => $days,
        ]);
    }

    protected function formatCalendarDay(
        Carbon $date,
        Carbon $today,
        Carbon $maxBookableDate,
        int $availableCount,
        int $totalCount,
        int $requiredCapacity,
        bool $multiRoomRequired = false
    ): array {
        $dateStr = $date->format('Y-m-d');

        if ($date->lt($today)) {
            return [
                'date' => $dateStr,
                'status' => 'past',
                'available_count' => 0,
                'total_count' => $totalCount,
                'multi_room_required' => false,
            ];
        }

        if ($date->gt($maxBookableDate)) {
            return [
                'date' => $dateStr,
                'status' => 'unavailable',
                'available_count' => 0,
                'total_count' => $totalCount,
                'multi_room_required' => false,
            ];
        }

        if ($availableCount < 1 || ($availableCount < $requiredCapacity && !$multiRoomRequired)) {
            $status = 'unavailable';
        } elseif ($multiRoomRequired) {
            $status = 'multi_room';
        } elseif ($totalCount > 0 && $availableCount <= max(1, (int) ceil($totalCount * 0.25))) {
            $status = 'limited';
        } else {
            $status = 'available';
        }

        return [
            'date' => $dateStr,
            'status' => $status,
            'available_count' => $availableCount,
            'total_count' => $totalCount,
            'multi_room_required' => $multiRoomRequired,
        ];
    }

    public function createReservation(Request $request): Response
    {
        $config = $this->getConfig();

        if (!$config['enabled']) {
            return response()->json(['message' => 'Las reservas web no están habilitadas en este momento.'], 503);
        }

        $validator = Validator::make($request->all(), [
            'reservation_type' => 'required|in:room,day_pass',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'nullable|date',
            'adults' => 'required|integer|min:1',
            'children' => 'integer|min:0',
            'infants' => 'integer|min:0',
            'room_type_id' => 'nullable|exists:room_types,id',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'special_requests' => 'nullable|string|max:1000',
            'customer' => 'required|array',
            'customer.customer_type' => 'required|in:person',
            'customer.dni' => 'required|string|max:20',
            'customer.name' => 'required|string|max:100',
            'customer.last_name' => 'required|string|max:100',
            'customer.email' => 'required|email|max:150',
            'customer.phone_number' => 'nullable|string|max:20',
        ]);

        if ($request->reservation_type === 'room') {
            $validator->addRules([
                'check_out_date' => 'required|date|after:check_in_date',
                'room_type_id' => 'required|exists:room_types,id',
            ]);
        }

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $paymentMode = $request->input('payment_mode');
        if (empty($config['payment_modes'][$paymentMode])) {
            return response()->json(['message' => 'El modo de pago seleccionado no está disponible.'], 422);
        }

        $customer = $this->resolveCustomer($request->input('customer'));

        $internalPayload = [
            'customer_id' => $customer->id,
            'reservation_type' => $request->reservation_type,
            'check_in_date' => $request->check_in_date,
            'check_out_date' => $request->check_out_date,
            'adults' => $request->adults,
            'children' => $request->children ?? 0,
            'infants' => $request->infants ?? 0,
            'room_type_id' => $request->room_type_id,
            'special_requests' => $request->special_requests,
            'contact_channel' => 'website',
            'referral_source' => 'social_media',
            'payment_status' => 'pending',
            'guests' => [[
                'first_name' => $customer->name,
                'last_name' => $customer->last_name,
                'document_number' => $customer->dni,
                'email' => $customer->email,
                'phone' => $customer->phone_number,
                'is_primary_guest' => true,
            ]],
        ];

        $internalRequest = Request::create('/api/reservations', 'POST', $internalPayload);
        $response = $this->reservationController->store($internalRequest);

        if ($response->getStatusCode() !== 201) {
            return $response;
        }

        $reservationData = json_decode($response->getContent(), true);
        $reservationId = $reservationData['id']
            ?? ($reservationData['main_reservation']['id'] ?? null);

        if (!$reservationId) {
            return response()->json(['message' => 'No se pudo registrar la reserva.'], 500);
        }

        $reservation = Reservation::findOrFail($reservationId);
        $this->applyWebBookingDefaults($reservation, $paymentMode, $config['deposit_percentage']);

        $message = 'Reserva registrada exitosamente. Recibirá confirmación por correo.';
        if ($this->requiresPaymentReceipt($paymentMode)) {
            $message = 'Reserva registrada. Adjunte el comprobante de pago para completar el proceso.';
        }
        if (!empty($reservationData['total_rooms']) && (int) $reservationData['total_rooms'] > 1) {
            $suffix = ' Recibirá confirmación por correo.';
            if ($this->requiresPaymentReceipt($paymentMode)) {
                $suffix = ' Adjunte el comprobante de pago para completar el proceso.';
            }
            $message = 'Reserva registrada con ' . (int) $reservationData['total_rooms'] . ' habitaciones.' . $suffix;
        }

        return response()->json([
            'message' => $message,
            'reservation' => $this->formatPublicReservation($reservation->fresh(['customer', 'roomType'])),
            'total_rooms' => $reservationData['total_rooms'] ?? 1,
            'requires_payment_receipt' => $this->requiresPaymentReceipt($paymentMode),
        ], 201);
    }

    public function uploadPaymentReceipt(Request $request, int $reservationId): Response
    {
        $validator = Validator::make($request->all(), [
            'customer_email' => 'required|email|max:150',
            'receipt' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $reservation = Reservation::with('customer')->find($reservationId);

        if (!$reservation || !$this->isWebReservation($reservation)) {
            return response()->json(['message' => 'Reserva no encontrada.'], 404);
        }

        if (!$this->requiresPaymentReceipt((string) $reservation->web_payment_mode)) {
            return response()->json(['message' => 'Esta reserva no requiere comprobante de pago.'], 422);
        }

        $customerEmail = strtolower(trim((string) $request->input('customer_email')));
        $reservationEmail = strtolower(trim((string) ($reservation->customer->email ?? '')));

        if ($customerEmail !== $reservationEmail) {
            return response()->json(['message' => 'No coincide el correo con la reserva.'], 403);
        }

        if ($reservation->web_payment_receipt_url) {
            return response()->json(['message' => 'Ya se recibió un comprobante para esta reserva.'], 422);
        }

        $path = $request->file('receipt')->store(
            'booking-receipts/' . $reservation->id,
            'public'
        );
        $url = Storage::disk('public')->url($path);

        $reservation->update([
            'web_payment_receipt_path' => $path,
            'web_payment_receipt_url' => $url,
            'web_payment_receipt_uploaded_at' => now(),
        ]);

        $reservation->childReservations()->update([
            'web_payment_receipt_path' => $path,
            'web_payment_receipt_url' => $url,
            'web_payment_receipt_uploaded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Comprobante recibido. Verificaremos el pago para confirmar su reserva.',
            'receipt_url' => $url,
            'reservation' => $this->formatPublicReservation($reservation->fresh(['customer', 'roomType'])),
        ], 201);
    }

    protected function isWebReservation(Reservation $reservation): bool
    {
        return $reservation->contact_channel === 'website' || !empty($reservation->web_payment_mode);
    }

    protected function applyWebBookingDefaults(
        Reservation $reservation,
        string $paymentMode,
        float $depositPercentage
    ): void {
        $depositAmount = $this->calculateDepositAmount($reservation, $paymentMode, $depositPercentage);
        $updates = [
            'status' => 'pending',
            'web_payment_mode' => $paymentMode,
            'deposit_amount' => $depositAmount,
            'payment_status' => 'pending',
        ];

        $reservation->update($updates);

        $reservation->childReservations()->update([
            'status' => 'pending',
            'web_payment_mode' => $paymentMode,
            'payment_status' => 'pending',
        ]);
    }

    protected function resolveCustomer(array $data): Customer
    {
        $existing = Customer::where('dni', $data['dni'])->first()
            ?? Customer::where('email', $data['email'])->first();

        if ($existing) {
            $existing->update([
                'name' => $data['name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? $existing->phone_number,
            ]);

            return $existing->fresh();
        }

        return Customer::create([
            'customer_type' => 'person',
            'dni' => $data['dni'],
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'active' => true,
        ]);
    }

    protected function calculateDepositAmount(Reservation $reservation, string $paymentMode, float $depositPercentage): float
    {
        if ($paymentMode !== 'deposit') {
            return 0;
        }

        $total = (float) ($reservation->final_price ?? $reservation->total_price ?? 0);

        return round($total * ($depositPercentage / 100), 2);
    }

    protected function formatPublicReservation(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'reservation_type' => $reservation->reservation_type,
            'status' => $reservation->status,
            'payment_status' => $reservation->payment_status,
            'web_payment_mode' => $reservation->web_payment_mode,
            'web_payment_receipt_url' => $reservation->web_payment_receipt_url,
            'web_payment_receipt_uploaded_at' => $reservation->web_payment_receipt_uploaded_at?->toIso8601String(),
            'check_in_date' => $reservation->check_in_date?->format('Y-m-d'),
            'check_out_date' => $reservation->check_out_date?->format('Y-m-d'),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'total_price' => $reservation->total_price,
            'deposit_amount' => $reservation->deposit_amount,
            'room_type' => $reservation->roomType ? [
                'id' => $reservation->roomType->id,
                'name' => $reservation->roomType->name,
            ] : null,
            'customer' => $reservation->customer ? [
                'name' => $reservation->customer->name,
                'last_name' => $reservation->customer->last_name,
                'email' => $reservation->customer->email,
            ] : null,
        ];
    }
}
