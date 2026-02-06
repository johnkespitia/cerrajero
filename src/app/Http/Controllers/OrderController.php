<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\KitchenRecipe;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\PaymentType;
use App\Services\ReservationValidationService;
use App\Services\InventoryVerificationService;
use App\Services\OrderPaymentService;
use App\Services\MealConsumptionService;
use Carbon\Carbon;
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
            'reservation.room',
            'paymentType',
            'mealConsumptions'
        ]);

        // Filtros
        if ($request->has('customer_id') && $request->customer_id !== '') {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('reservation_id') && $request->reservation_id !== '') {
            $query->where('reservation_id', $request->reservation_id);
        }

        if ($request->has('meal_type') && $request->meal_type !== '') {
            $query->where('meal_type', $request->meal_type);
        }

        if ($request->has('date_from') && $request->date_from !== '') {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->has('date_to') && $request->date_to !== '') {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Orden descendente: más recientes primero; limitar a 50 registros
        $orders = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->take(50)->get();

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
        $rules = [
            'user_id' => 'required|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'meal_type' => 'required|in:breakfast,lunch,dinner',
            'charge_to_room' => 'boolean',
            'payment_type_id' => 'nullable|exists:payment_types,id',
            'external_reference' => 'nullable|string|max:20',
            'order_items' => 'nullable|array',
            'order_items.*.recipe_id' => 'required_with:order_items|exists:kitchen_recipes,id',
            'order_items.*.quantity' => 'required_with:order_items|numeric|min:0.01',
            'order_items.*.measure_id' => 'required_with:order_items|exists:inventory_measures,id',
            'order_items.*.unit_price' => 'nullable|numeric|min:0',
        ];

        $hasItems = $request->has('order_items') && is_array($request->order_items) && count($request->order_items) > 0;
        if (!$hasItems) {
            $rules['price'] = 'required|numeric|min:0';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $reservation = null;
            $totalPrice = 0;
            $itemsToCreate = [];

            if ($hasItems) {
                $defaultMeasureId = \App\Models\InventoryMeasure::first()?->id;
                foreach ($request->order_items as $idx => $item) {
                    $recipe = KitchenRecipe::find($item['recipe_id']);
                    $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                        ? (float) $item['unit_price']
                        : (float) ($recipe->default_price ?? 0);
                    if ($unitPrice < 0) {
                        return response()->json([
                            'message' => "El plato \"{$recipe->name}\" no tiene precio. Configure precio por defecto o envíe unit_price.",
                            'errors' => ["order_items.{$idx}" => 'Precio requerido'],
                        ], 422);
                    }
                    $qty = (float) $item['quantity'];
                    $totalPrice += $unitPrice * $qty;
                    $itemsToCreate[] = [
                        'recipe_id' => $item['recipe_id'],
                        'quantity' => $qty,
                        'measure_id' => $item['measure_id'] ?? $defaultMeasureId,
                        'unit_price' => $unitPrice,
                    ];
                }
            } else {
                $totalPrice = (float) $request->price;
            }

            // Validar carga a habitación
            if ($request->charge_to_room) {
                // Si se proporciona reservation_id, validar que exista y esté activa
                if ($request->reservation_id) {
                    $reservation = Reservation::findOrFail($request->reservation_id);
                    
                    if (!$this->reservationValidationService->isReservationActive($reservation)) {
                        return response()->json([
                            'message' => 'La reserva especificada no está activa',
                            'reservation_status' => $reservation->status
                        ], 422);
                    }
                    
                    // Validar que el customer_id coincida con la reserva
                    if ($reservation->customer_id != $request->customer_id) {
                        return response()->json([
                            'message' => 'El cliente no coincide con la reserva'
                        ], 422);
                    }
                } else {
                    // Si no se proporciona reservation_id, buscar automáticamente la reserva activa
                    $reservation = $this->reservationValidationService->getActiveReservationForCustomer(
                        $request->customer_id
                    );
                    
                    if (!$reservation) {
                        return response()->json([
                            'message' => 'No se puede cargar a habitación. El cliente no tiene una reserva activa'
                        ], 422);
                    }
                }

                // Pasadía: no se puede cargar a habitación; debe pagar de inmediato
                if ($reservation->reservation_type === 'day_pass') {
                    return response()->json([
                        'message' => 'En pasadía no se puede cargar a habitación; debe pagar la orden de inmediato.'
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

            // Determinar reservation_id: usar la encontrada automáticamente o la proporcionada
            $reservationId = $reservation ? $reservation->id : $request->reservation_id;
            
            // Crear orden
            $order = Order::create([
                'user_id' => $request->user_id,
                'customer_id' => $request->customer_id,
                'reservation_id' => $reservationId,
                'meal_type' => $request->meal_type,
                'charge_to_room' => $request->charge_to_room ?? false,
                'payment_type_id' => $request->charge_to_room ? null : $request->payment_type_id,
                'external_reference' => $request->external_reference,
                'price' => $totalPrice,
                'inventory_verified' => $hasItems,
                'inventory_verification_date' => $hasItems ? now() : null,
            ]);

            // Crear order_items si se enviaron
            foreach ($itemsToCreate as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'recipe_id' => $item['recipe_id'],
                    'quantity' => $item['quantity'],
                    'measure_id' => $item['measure_id'],
                    'unit_price' => $item['unit_price'],
                    'status' => 'pending',
                ]);
            }

            // Registrar consumo de alimentación (incluido + adicional) ANTES de cargar a habitación
            if ($reservationId) {
                $reservationForMeal = $reservation ?? Reservation::find($reservationId);
                if ($reservationForMeal) {
                    $totalPlates = $hasItems
                        ? (int) collect($itemsToCreate)->sum('quantity')
                        : 1;
                    $this->mealConsumptionService->registerConsumptionSplit(
                        $reservationForMeal,
                        $order,
                        $request->meal_type,
                        $totalPlates
                    );
                }
            }

            // Si carga a habitación, crear pago en reserva (solo por la parte adicional)
            if ($request->charge_to_room && $reservation) {
                $this->orderPaymentService->chargeToRoom($order, $reservation);
            }

            // Si NO carga a habitación y tiene método de pago, procesar pago (solo parte adicional si hay reserva con consumos)
            if (!$request->charge_to_room && $request->payment_type_id) {
                $paymentType = PaymentType::findOrFail($request->payment_type_id);
                $amountToPay = $reservationId
                    ? $this->orderPaymentService->getAdditionalAmountForOrder($order)
                    : $totalPrice;
                $this->orderPaymentService->processPayment($order, $paymentType, $amountToPay);
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
