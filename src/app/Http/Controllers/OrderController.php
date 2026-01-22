<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\PaymentType;
use App\Services\ReservationValidationService;
use App\Services\InventoryVerificationService;
use App\Services\OrderPaymentService;
use App\Services\MealConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $reservationValidationService;
    protected $inventoryVerificationService;
    protected $orderPaymentService;
    protected $mealConsumptionService;

    public function __construct(
        ReservationValidationService $reservationValidationService,
        InventoryVerificationService $inventoryVerificationService,
        OrderPaymentService $orderPaymentService,
        MealConsumptionService $mealConsumptionService
    ) {
        $this->reservationValidationService = $reservationValidationService;
        $this->inventoryVerificationService = $inventoryVerificationService;
        $this->orderPaymentService = $orderPaymentService;
        $this->mealConsumptionService = $mealConsumptionService;
    }

    public function index(Request $request)
    {
        $query = Order::with([
            'orderItems.recipe',
            'orderItems.batchs',
            'orderItems.measure',
            'user',
            'customer',
            'reservation',
            'paymentType'
        ]);

        // Filtros
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('reservation_id')) {
            $query->where('reservation_id', $request->reservation_id);
        }

        if ($request->has('meal_type')) {
            $query->where('meal_type', $request->meal_type);
        }

        $orders = $query->get();

        return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->load([
            'user',
            'customer',
            'reservation',
            'paymentType',
            'orderItems.recipe',
            'orderItems.batchs',
            'orderItems.measure',
            'orderPayments.paymentType',
            'mealConsumptions'
        ]);

        return response()->json($order);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'meal_type' => 'required|in:breakfast,lunch,dinner',
            'charge_to_room' => 'boolean',
            'payment_type_id' => 'nullable|exists:payment_types,id',
            'external_reference' => 'nullable|string|max:20',
            'price' => 'required|numeric|min:0',
            'order_items' => 'nullable|array', // Items para validar inventario
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            // Validar reserva activa si se proporciona reservation_id
            if ($request->reservation_id) {
                $reservation = Reservation::findOrFail($request->reservation_id);
                
                if (!$this->reservationValidationService->isReservationActive($reservation)) {
                    return response()->json([
                        'message' => 'La reserva no está activa',
                        'reservation_status' => $reservation->status
                    ], 422);
                }

                // Validar que el customer_id coincida con la reserva
                if ($reservation->customer_id != $request->customer_id) {
                    return response()->json([
                        'message' => 'El cliente no coincide con la reserva'
                    ], 422);
                }
            }

            // Validar carga a habitación
            if ($request->charge_to_room) {
                if (!$request->reservation_id) {
                    return response()->json([
                        'message' => 'No se puede cargar a habitación sin una reserva activa'
                    ], 422);
                }
            } else {
                // Si NO carga a habitación, validar método de pago (sin crédito)
                if (!$request->payment_type_id) {
                    return response()->json([
                        'message' => 'Se requiere método de pago cuando no se carga a habitación'
                    ], 422);
                }

                $paymentType = PaymentType::findOrFail($request->payment_type_id);
                if ($paymentType->credit) {
                    return response()->json([
                        'message' => 'No se pueden usar métodos de pago a crédito para órdenes de restaurante'
                    ], 422);
                }
            }

            // Validar inventario antes de crear orden
            if ($request->has('order_items') && count($request->order_items) > 0) {
                $inventoryCheck = $this->inventoryVerificationService->checkInventoryBeforeCreate($request->order_items);
                
                if (!$inventoryCheck['available']) {
                    return response()->json([
                        'message' => 'No hay suficiente inventario para crear la orden',
                        'errors' => $inventoryCheck['errors']
                    ], 422);
                }
            }

            // Crear orden
            $order = Order::create([
                'user_id' => $request->user_id,
                'customer_id' => $request->customer_id,
                'reservation_id' => $request->reservation_id,
                'meal_type' => $request->meal_type,
                'charge_to_room' => $request->charge_to_room ?? false,
                'payment_type_id' => $request->charge_to_room ? null : $request->payment_type_id,
                'external_reference' => $request->external_reference,
                'price' => $request->price,
                'inventory_verified' => $request->has('order_items') && count($request->order_items) > 0,
                'inventory_verification_date' => $request->has('order_items') && count($request->order_items) > 0 ? now() : null,
            ]);

            // Si carga a habitación, crear pago en reserva
            if ($request->charge_to_room && $request->reservation_id) {
                $reservation = Reservation::findOrFail($request->reservation_id);
                $this->orderPaymentService->chargeToRoom($order, $reservation);
            }

            // Si NO carga a habitación y tiene método de pago, procesar pago
            if (!$request->charge_to_room && $request->payment_type_id) {
                $paymentType = PaymentType::findOrFail($request->payment_type_id);
                $this->orderPaymentService->processPayment($order, $paymentType, $request->price);
            }

            // Registrar consumo de alimentación si hay reserva
            if ($request->reservation_id) {
                $reservation = Reservation::findOrFail($request->reservation_id);
                $canConsumeIncluded = $reservation->canConsumeIncludedMeal($request->meal_type);
                $this->mealConsumptionService->registerConsumption(
                    $reservation,
                    $order,
                    $request->meal_type,
                    $canConsumeIncluded
                );
            }

            DB::commit();

            return response()->json($order->load([
                'customer',
                'reservation',
                'paymentType',
                'orderItems'
            ]), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,id',
            'customer_id' => 'sometimes|exists:customers,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'meal_type' => 'sometimes|in:breakfast,lunch,dinner',
            'charge_to_room' => 'boolean',
            'payment_type_id' => 'nullable|exists:payment_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // No permitir cambiar charge_to_room si ya está pagada
        if ($order->paid && $request->has('charge_to_room') && $request->charge_to_room != $order->charge_to_room) {
            return response()->json([
                'message' => 'No se puede cambiar el método de pago de una orden ya pagada'
            ], 422);
        }

        $order->update($request->all());

        return response()->json($order->load([
            'customer',
            'reservation',
            'paymentType'
        ]));
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json(null, 204);
    }

    /**
     * Obtener reserva activa de un cliente
     */
    public function getActiveReservation(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $reservation = $this->reservationValidationService->getActiveReservationForCustomer(
            $request->customer_id
        );

        return response()->json([
            'has_active_reservation' => $reservation !== null,
            'reservation' => $reservation ? $reservation->load(['customer', 'room', 'roomType', 'additionalServices.additionalService']) : null
        ]);
    }

    /**
     * Obtener consumo de alimentación de una reserva
     */
    public function getMealConsumption($reservationId, Request $request)
    {
        $reservation = Reservation::findOrFail($reservationId);
        
        $date = $request->has('date') ? $request->date : null;
        $summary = $this->mealConsumptionService->getConsumptionSummary($reservation, $date);

        return response()->json($summary);
    }

    /**
     * Verificar inventario de una orden
     */
    public function verifyInventory(Order $order)
    {
        $verification = $this->inventoryVerificationService->verifyOrderInventory($order);

        if ($verification['available']) {
            $order->update([
                'inventory_verified' => true,
                'inventory_verification_date' => now(),
            ]);
        }

        return response()->json($verification);
    }
}
