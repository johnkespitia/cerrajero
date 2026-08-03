<?php

namespace App\Http\Controllers;

use App\Models\KioskInvoice;
use App\Models\PaymentType;
use App\Models\KioskUnit;
use App\Models\KioskInvoiceDetail;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionInvalidPayloadException;
use App\Services\ElectronicInvoicing\Exceptions\KioskEmissionUnavailableException;
use App\Services\ElectronicInvoicing\KioskFiscalSnapshotBuilder;
use App\Services\ElectronicInvoicing\KioskInvoiceEmissionService;
use App\Services\CashRegisterClosureService;
use App\Services\KioskDiscountService;
use App\Services\KioskOtpService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class KioskInvoiceController extends Controller
{
    protected $otpService;
    protected CashRegisterClosureService $closureService;
    protected KioskDiscountService $discountService;

    public function __construct(
        KioskOtpService $otpService,
        CashRegisterClosureService $closureService,
        KioskDiscountService $discountService
    ) {
        $this->otpService = $otpService;
        $this->closureService = $closureService;
        $this->discountService = $discountService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskInvoice::with([
            "customer",
            "payment_type",
            "details.kiosk_unit.product.tax",
            "details.kiosk_unit.product.category",
            "manualDiscountBy:id,name",
        ])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Generar y enviar OTP para compra a crédito
     */
    public function generateOtp(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'payment_code' => 'required',
            'payment_type_id' => 'required|exists:payment_types,id',
            'units' => 'required|array',
            'units.*.kiosk_units_id' => 'required|exists:kiosk_units,id',
            'units.*.price' => 'required|numeric|min:0',
            'electronic_invoice' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Buscar reserva activa (usando misma lógica que store)
            $activeReservation = $this->findActiveReservation($request->customer_id, $request->reservation_id);
            
            $paymentType = PaymentType::find($request->payment_type_id);
            
            if (!$activeReservation && $paymentType->credit) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se puede usar métodos de pago a crédito si el cliente no tiene una reserva activa.',
                    'errors' => ['payment_type_id' => ['Los métodos de pago a crédito solo están disponibles para clientes con reserva activa.']]
                ], 422);
            }

            if (!$paymentType->credit) {
                DB::rollBack();
                return response()->json([
                    'message' => 'El OTP solo es requerido para métodos de pago a crédito.',
                ], 422);
            }

            // Crear factura temporal
            $tempInvoice = KioskInvoice::create([
                'customer_id' => $request->customer_id,
                'reservation_id' => $activeReservation->id,
                'payment_code' => $request->payment_code,
                'payment_type_id' => $request->payment_type_id,
                'payed' => false,
                'electronic_invoice' => $request->electronic_invoice
            ]);

            // Guardar unidades temporalmente (en sesión o tabla temporal)
            // Por ahora, guardamos en la factura pero no marcamos como vendidas
            $snapshotBuilder = new KioskFiscalSnapshotBuilder();
            $units = $request->get("units");
            foreach ($units as $unit) {
                $unitModel = KioskUnit::with('product.tax')->find($unit['kiosk_units_id']);
                $snapshot = $snapshotBuilder->buildForUnit($unitModel, $unit['price']);
                KioskInvoiceDetail::create(array_merge([
                    'kiosk_invoices_id' => $tempInvoice->id,
                    'kiosk_units_id' => $unit['kiosk_units_id'],
                    'price' => $unit['price'],
                ], $snapshot));
            }

            // Generar y enviar OTP
            $this->otpService->generateAndSendOtp($tempInvoice, $activeReservation);

            DB::commit();

            return response()->json([
                'message' => 'Código de verificación (OTP) enviado al email del huésped principal.',
                'invoice_id' => $tempInvoice->id,
                'otp_sent' => true,
                'expires_at' => $tempInvoice->otp_expires_at
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generando OTP: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al generar código de verificación: ' . $e->getMessage(),
                'errors' => ['otp' => ['No se pudo enviar el código de verificación']]
            ], 500);
        }
    }

    /**
     * Verificar OTP y completar factura
     */
    public function verifyOtpAndComplete(Request $request, KioskInvoice $kioskInvoice)
    {
        $request->validate([
            'otp_code' => 'required|string|size:6',
            'coupon_code' => 'nullable|string|max:50',
            'manual_discount' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Verificar OTP
            $verification = $this->otpService->verifyOtp($kioskInvoice, $request->otp_code, auth()->id());
            
            if (!$verification['valid']) {
                DB::rollBack();
                return response()->json([
                    'message' => $verification['message'],
                    'errors' => ['otp_code' => [$verification['message']]]
                ], 422);
            }

            // OTP verificado, ahora completar la factura
            // Marcar unidades como vendidas
            $details = $kioskInvoice->details;
            $total_invoice = 0;
            
            foreach ($details as $detail) {
                $unitModel = KioskUnit::find($detail->kiosk_units_id);
                if ($unitModel) {
                    $unitModel->sold = true;
                    $unitModel->save();
                    $total_invoice += $detail->price;
                }
            }

            $discount = $this->discountService->resolve(
                $total_invoice,
                $request->input('coupon_code'),
                $request->input('manual_discount')
            );
            $this->discountService->applyToInvoice($kioskInvoice, $discount, auth()->id(), true);
            $payable = $discount['payable'];

            // Actualizar factura
            if ($request->has('payed_value') && $request->payed_value > 0) {
                $kioskInvoice->payed_value = $request->payed_value;
                $kioskInvoice->remain_money = $request->payed_value - $payable;
            }
            $kioskInvoice->save();

            // Crear pago en reserva (si aplica)
            if ($kioskInvoice->reservation_id && $kioskInvoice->payment_type->credit) {
                ReservationPayment::create([
                    'reservation_id' => $kioskInvoice->reservation_id,
                    'amount' => $payable,
                    'concept' => 'Compra en kiosko (a crédito)',
                    'payment_type_id' => $kioskInvoice->payment_type_id,
                    'payment_reference' => $kioskInvoice->payment_code,
                    'notes' => "Factura kiosko #{$kioskInvoice->id} - Pendiente de pago",
                    'created_by' => auth()->id(),
                ]);
            }

            // Asignar a cierre de caja (crea cierre abierto del día si aún no existe)
            $this->closureService->assignInvoice($kioskInvoice, $request->user()->id);
            if ($kioskInvoice->closure_id) {
                $this->closureService->refreshTotals($kioskInvoice->closure);
            }

            $electronicEnvelope = [];
            if ($this->electronicEmissionEnabled()) {
                try {
                    $electronicEnvelope = $this->attemptElectronicEmission(
                        $kioskInvoice,
                        $request->input('acquirer')
                    );
                } catch (KioskEmissionInvalidPayloadException $ke) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $ke->getMessage(),
                        'errors' => [
                            'acquirer' => [$ke->getMessage()],
                        ],
                        'electronic_document_error' => [
                            'code' => $ke->emissionCode(),
                            'message' => $ke->getMessage(),
                        ],
                    ], 422);
                }
            }

            DB::commit();

            $kioskInvoice->load(['details.kiosk_unit.product', 'payment_type', 'customer', 'reservation', 'manualDiscountBy:id,name']);

            $response = [
                'message' => 'Compra completada exitosamente.',
                'invoice' => $kioskInvoice,
            ];
            foreach ($electronicEnvelope as $key => $value) {
                $response[$key] = $value;
            }
            return response()->json($response, 200);
        } catch (ValidationException $ve) {
            DB::rollBack();
            $errors = $ve->errors();
            $firstMessage = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstMessage ?: 'Datos de factura inválidos.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error completando factura con OTP: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al completar la compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper para encontrar reserva activa (lógica compartida)
     */
    protected function findActiveReservation($customerId, $reservationId = null)
    {
        if ($reservationId) {
            return Reservation::where('id', $reservationId)
                ->where('customer_id', $customerId)
                ->where('status', 'checked_in')
                ->first();
        }

        $activeReservations = Reservation::where('customer_id', $customerId)
            ->where('status', 'checked_in')
            ->orderBy('check_in_date', 'desc')
            ->get();
        
        if ($activeReservations->count() === 0) {
            return null;
        } elseif ($activeReservations->count() === 1) {
            return $activeReservations->first();
        } else {
            // Lógica inteligente: priorizar reserva principal de grupo, luego más días restantes
            return $activeReservations->sortByDesc(function($reservation) {
                $daysRemaining = 0;
                if ($reservation->check_out_date) {
                    $daysRemaining = now()->diffInDays($reservation->check_out_date, false);
                }
                $priority = 0;
                if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
                    $priority = 1000;
                }
                return $priority + max(0, $daysRemaining);
            })->first();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try{
            $isPendingWalkIn = $request->boolean('pending');

            $rules = [
                'customer_id' => 'required|exists:customers,id',
                'reservation_id' => 'nullable|exists:reservations,id',
                'units'=> 'required|array',
                'units.*.kiosk_units_id' => 'required|exists:kiosk_units,id',
                'units.*.price' => 'required|numeric|min:0',
                'electronic_invoice' => 'required|boolean',
                'payed_value' => 'numeric',
                'pending' => 'nullable|boolean',
                'temp_invoice_id' => 'nullable|exists:kiosk_invoices,id',
                'otp_code' => 'nullable|string|size:6',
                'coupon_code' => 'nullable|string|max:50',
                'manual_discount' => 'nullable|numeric|min:0',
                'acquirer' => 'nullable|array',
                'acquirer.document_type' => 'required_with:acquirer|string|max:30',
                'acquirer.document_number' => 'required_with:acquirer|string|max:30',
                'acquirer.legal_name' => 'required_with:acquirer|string|max:200',
                'acquirer.dv' => 'nullable|integer|min:0|max:9',
                'acquirer.email' => 'nullable|email|max:200',
                'acquirer.address_line' => 'nullable|string|max:255',
                'acquirer.city_code_dian' => 'nullable|string|max:5',
                'acquirer.country_code' => 'nullable|string|size:2',
                'acquirer.tax_regime_code' => 'nullable|string|max:4',
                'acquirer.tax_responsibilities' => 'nullable|array',
            ];

            if ($isPendingWalkIn) {
                $rules['payment_code'] = 'nullable|string|max:50';
                $rules['payment_type_id'] = 'nullable|exists:payment_types,id';
            } else {
                $rules['payment_code'] = 'required';
                $rules['payment_type_id'] = 'required|exists:payment_types,id';
            }

            $request->validate($rules);

            if ($this->electronicEmissionEnabled()
                && $request->boolean('electronic_invoice')
                && empty($request->input('acquirer'))) {
                DB::rollBack();
                return response()->json([
                    'message' => 'electronic_invoice=true requiere los datos fiscales del adquiriente.',
                    'errors' => [
                        'acquirer' => ['Debe enviar el bloque acquirer cuando electronic_invoice es true.'],
                    ],
                    'electronic_document_error' => [
                        'code' => KioskEmissionInvalidPayloadException::CODE_MISSING_ACQUIRER,
                        'message' => 'electronic_invoice=true requires an acquirer block.',
                    ],
                ], 422);
            }

            // OPCIÓN C: Asociación Automática Inteligente de Reservas
            $activeReservation = $this->findActiveReservation($request->customer_id, $request->reservation_id);
            
            if ($request->has('reservation_id') && $request->reservation_id && !$activeReservation) {
                DB::rollBack();
                return response()->json([
                    'message' => 'La reserva especificada no existe, no pertenece al cliente o no está en estado checked_in.',
                    'errors' => ['reservation_id' => ['Reserva inválida o no disponible']]
                ], 422);
            }

            // Pendiente walk-in: no usa rama crédito/OTP
            if ($isPendingWalkIn) {
                if ($request->filled('payment_type_id')) {
                    $pendingPaymentType = PaymentType::find($request->payment_type_id);
                    if ($pendingPaymentType && $pendingPaymentType->credit) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Para cargo a habitación use el flujo de crédito con OTP, no "dejar pendiente".',
                            'errors' => ['pending' => ['El pendiente walk-in no aplica a métodos a crédito.']]
                        ], 422);
                    }
                }

                $kioskInvoice = KioskInvoice::create([
                    'customer_id' => $request->customer_id,
                    'reservation_id' => $activeReservation?->id,
                    'payment_code' => $request->payment_code,
                    'payment_type_id' => $request->payment_type_id,
                    'payed' => false,
                    'electronic_invoice' => $request->boolean('electronic_invoice'),
                ]);

                $units = $request->get('units');
                $total_invoice = 0;
                $snapshotBuilder = new KioskFiscalSnapshotBuilder();
                foreach ($units as $unit) {
                    $unitModel = KioskUnit::with('product.tax')->find($unit['kiosk_units_id']);
                    $snapshot = $snapshotBuilder->buildForUnit($unitModel, $unit['price'] ?? 0);
                    KioskInvoiceDetail::create(array_merge([
                        'kiosk_invoices_id' => $kioskInvoice->id,
                        'kiosk_units_id' => $unit['kiosk_units_id'],
                        'price' => $unit['price'],
                    ], $snapshot));
                    $unitModel->sold = true;
                    $unitModel->save();
                    $total_invoice += $unit['price'];
                }

                $this->closureService->assignInvoice($kioskInvoice, $request->user()->id);

                DB::commit();
                $kioskInvoice->load(['details.kiosk_unit.product', 'payment_type', 'customer', 'reservation']);
                return response()->json($kioskInvoice, 201);
            }

            // Obtener el tipo de pago
            $paymentType = PaymentType::find($request->payment_type_id);

            // REGLA 1: Si el cliente NO tiene reserva activa, no puede usar métodos con credit = 1
            if (!$activeReservation && $paymentType->credit) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se puede usar métodos de pago a crédito si el cliente no tiene una reserva activa.',
                    'errors' => ['payment_type_id' => ['Los métodos de pago a crédito solo están disponibles para clientes con reserva activa.']]
                ], 422);
            }

            // REGLA OTP: Si es método a crédito, verificar OTP
            $tempInvoice = null;
            if ($paymentType->credit && $activeReservation) {
                if (!$request->has('temp_invoice_id') || !$request->temp_invoice_id) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Se requiere código de verificación (OTP) para compras a crédito. Use el endpoint /kiosk/invoice/generate-otp primero.',
                        'requires_otp' => true,
                        'errors' => ['temp_invoice_id' => ['Debe solicitar el código OTP antes de completar la compra']]
                    ], 422);
                }
                
                // Obtener factura temporal
                $tempInvoice = KioskInvoice::find($request->temp_invoice_id);
                if (!$tempInvoice || !$tempInvoice->otp_code) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Factura temporal no encontrada o sin OTP generado.',
                        'errors' => ['temp_invoice_id' => ['Factura temporal inválida']]
                    ], 422);
                }
                
                // Verificar OTP
                if (!$request->has('otp_code') || !$request->otp_code) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Se requiere código de verificación (OTP).',
                        'errors' => ['otp_code' => ['Debe ingresar el código OTP recibido por email']]
                    ], 422);
                }
                
                $verification = $this->otpService->verifyOtp($tempInvoice, $request->otp_code, auth()->id());
                if (!$verification['valid']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $verification['message'],
                        'errors' => ['otp_code' => [$verification['message']]]
                    ], 422);
                }
                
                // OTP verificado, usar la factura temporal como base
                $kioskInvoice = $tempInvoice;
                // Actualizar datos si han cambiado
                $kioskInvoice->update([
                    'payment_code' => $request->payment_code,
                    'electronic_invoice' => $request->electronic_invoice
                ]);
            } else {
                // Para compras sin crédito, crear nueva factura
                $requestBody = $request->only([
                    'customer_id',
                    'payment_code',
                    'payment_type_id',
                    'payed_value',
                    'electronic_invoice',
                ]);
                $requestBody['payed'] = !$paymentType->credit;
                
                if ($activeReservation) {
                    $requestBody['reservation_id'] = $activeReservation->id;
                }

                $kioskInvoice = KioskInvoice::create($requestBody);
            }
            
            // Procesar unidades
            $units = $request->get("units");
            $total_invoice = 0;
            
            // Si es factura temporal (con OTP), las unidades ya están creadas
            if ($tempInvoice) {
                // Solo marcar como vendidas y calcular total
                foreach ($kioskInvoice->details as $detail) {
                    $unitModel = KioskUnit::find($detail->kiosk_units_id);
                    if ($unitModel) {
                        $unitModel->sold = true;
                        $unitModel->save();
                        $total_invoice += $detail->price;
                    }
                }
            } else {
                // Crear nuevas unidades
                $snapshotBuilder = new KioskFiscalSnapshotBuilder();
                foreach ($units as $key => $unit) {
                    $unitModel = KioskUnit::with('product.tax')->find($unit['kiosk_units_id']);
                    $unit['kiosk_invoices_id'] = $kioskInvoice->id;
                    $snapshot = $snapshotBuilder->buildForUnit($unitModel, $unit['price'] ?? 0);
                    $unit_saved = KioskInvoiceDetail::create(array_merge($unit, $snapshot));
                    $unitModel->sold = true;
                    $unitModel->save();
                    $total_invoice += (float) $unit['price'];
                }
            }
            $discount = $this->discountService->resolve(
                $total_invoice,
                $request->input('coupon_code'),
                $request->input('manual_discount')
            );
            $this->discountService->applyToInvoice($kioskInvoice, $discount, auth()->id(), true);
            $payable = $discount['payable'];

            if($request->get('payed_value') > 0){
                $kioskInvoice->remain_money = $kioskInvoice->payed_value - $payable;
                $kioskInvoice->save();
            }

            // REGLA 2 y 3: Si hay reserva activa, crear pago en la reserva
            if ($activeReservation) {
                if ($paymentType->credit) {
                    // REGLA 2: credit = 1 → crear pago PENDIENTE en reserva
                    ReservationPayment::create([
                        'reservation_id' => $activeReservation->id,
                        'amount' => $payable,
                        'concept' => 'Compra en kiosko (a crédito)',
                        'payment_type_id' => $paymentType->id,
                        'payment_reference' => $kioskInvoice->payment_code,
                        'notes' => "Factura kiosko #{$kioskInvoice->id} - Pendiente de pago",
                        'created_by' => auth()->id(),
                    ]);
                    
                    // REGLA 3: Si la reserva estaba "paid", cambiar a "partial" para habilitar botón de agregar pago
                    // Esto permite que el frontend muestre el botón de agregar pago cuando hay cargos a habitación
                    if ($activeReservation->payment_status === 'paid') {
                        $activeReservation->payment_status = 'partial';
                        $activeReservation->save();
                    }
                } else {
                    // REGLA 3: credit = 0 → crear pago PAGADO en reserva
                    ReservationPayment::create([
                        'reservation_id' => $activeReservation->id,
                        'amount' => $payable,
                        'concept' => 'Compra en kiosko',
                        'payment_type_id' => $paymentType->id,
                        'payment_reference' => $kioskInvoice->payment_code,
                        'notes' => "Factura kiosko #{$kioskInvoice->id}",
                        'created_by' => auth()->id(),
                    ]);

                    // Actualizar estado de pago de la reserva (excluyendo pagos a crédito del kiosko)
                    $totalPaid = $activeReservation->payments()
                        ->where(function($query) {
                            $query->where('concept', '!=', 'Compra en kiosko (a crédito)')
                                  ->orWhereNull('concept');
                        })
                        ->sum('amount');
                    $finalPrice = $activeReservation->final_price ?? $activeReservation->total_price;
                    
                    // Verificar solo facturas pendientes de esta reserva (no de otras estancias del cliente)
                    $pendingKioskInvoices = $activeReservation->kioskInvoices()
                        ->whereHas('payment_type', function ($query) {
                            $query->where('credit', true);
                        })
                        ->where('payed', false)
                        ->whereNull('cancelled_at')
                        ->with('details')
                        ->get();

                    $totalPendingKiosk = $pendingKioskInvoices->sum(function ($invoice) {
                        return $invoice->payableTotal();
                    });
                    
                    // Si hay cargos a habitación pendientes, siempre 'partial'
                    if ($totalPendingKiosk > 0) {
                        $activeReservation->payment_status = 'partial';
                    } elseif ($totalPaid >= $finalPrice) {
                        $activeReservation->payment_status = 'paid';
                    } elseif ($totalPaid > 0) {
                        $activeReservation->payment_status = 'partial';
                    }
                    $activeReservation->save();
                }
            }

            // Asignar a cierre de caja (crea cierre abierto del día si aún no existe)
            $this->closureService->assignInvoice($kioskInvoice, $request->user()->id);
            if ($kioskInvoice->closure_id) {
                $this->closureService->refreshTotals($kioskInvoice->closure);
            }

            $electronicEnvelope = [];
            if ($this->electronicEmissionEnabled()) {
                try {
                    $electronicEnvelope = $this->attemptElectronicEmission(
                        $kioskInvoice,
                        $request->input('acquirer')
                    );
                } catch (KioskEmissionInvalidPayloadException $ke) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $ke->getMessage(),
                        'errors' => [
                            'acquirer' => [$ke->getMessage()],
                        ],
                        'electronic_document_error' => [
                            'code' => $ke->emissionCode(),
                            'message' => $ke->getMessage(),
                        ],
                    ], 422);
                }
            }

            DB::commit();

            $kioskInvoice->load(['details', 'payment_type', 'customer', 'reservation', 'manualDiscountBy:id,name']);

            $payload = $kioskInvoice->toArray();
            foreach ($electronicEnvelope as $key => $value) {
                $payload[$key] = $value;
            }
            return response()->json($payload, 201);
        }catch(ValidationException $ve){
            DB::rollBack();
            $errors = $ve->errors();
            $firstMessage = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstMessage ?: 'Datos de factura inválidos.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear factura de kiosko: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear la factura: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }

    }

    /**
     * Helper para emitir el ElectronicDocument local asociado a una KioskInvoice.
     *
     * - Retorna { electronic_document: {...} } cuando la emisi\u00f3n local prospera.
     * - Retorna { electronic_document_error: {...} } cuando hay gaps de configuraci\u00f3n
     *   fiscal en entornos no productivos para no bloquear caja.
     * - Re-lanza KioskEmissionInvalidPayloadException para que el caller responda 422.
     */
    protected function attemptElectronicEmission(KioskInvoice $invoice, ?array $acquirerPayload): array
    {
        /** @var KioskInvoiceEmissionService $service */
        $service = app(KioskInvoiceEmissionService::class);

        try {
            $document = $service->emitForKioskInvoice($invoice, $acquirerPayload);
            return ['electronic_document' => $service->summarise($document)];
        } catch (KioskEmissionInvalidPayloadException $e) {
            throw $e;
        } catch (KioskEmissionUnavailableException $e) {
            Log::warning('Electronic emission unavailable for kiosk invoice', [
                'kiosk_invoice_id' => $invoice->id,
                'code' => $e->emissionCode(),
                'message' => $e->getMessage(),
            ]);
            return [
                'electronic_document_error' => [
                    'code' => $e->emissionCode(),
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    protected function electronicEmissionEnabled(): bool
    {
        $value = function_exists('config') ? config('electronic-invoicing.enabled', false) : false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * Display the specified resource.
     */
    public function show(KioskInvoice $kioskInvoice)
    {
        $kioskInvoice->load([
            'customer',
            'payment_type',
            'reservation',
            'details.kiosk_unit.product.tax',
            'details.kiosk_unit.product.category',
            'manualDiscountBy:id,name',
        ]);

        return $kioskInvoice;
    }

    /**
     * Sincronizar líneas de una factura pendiente (agregar/quitar productos).
     */
    public function syncDetails(Request $request, KioskInvoice $kioskInvoice)
    {
        if (!$kioskInvoice->isPending()) {
            return response()->json([
                'message' => 'Solo se pueden editar facturas pendientes.',
                'errors' => ['invoice' => ['La factura no está pendiente']]
            ], 422);
        }

        $request->validate([
            'units' => 'required|array|min:1',
            'units.*.kiosk_units_id' => 'required|exists:kiosk_units,id',
            'units.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $kioskInvoice->load('details');
            $newUnitIds = collect($request->units)->pluck('kiosk_units_id')->map(fn ($id) => (int) $id)->all();
            $existingByUnitId = $kioskInvoice->details->keyBy('kiosk_units_id');

            // Quitar unidades que ya no están
            foreach ($kioskInvoice->details as $detail) {
                if (!in_array((int) $detail->kiosk_units_id, $newUnitIds, true)) {
                    $unitModel = KioskUnit::find($detail->kiosk_units_id);
                    if ($unitModel) {
                        $unitModel->sold = false;
                        $unitModel->save();
                    }
                    $detail->delete();
                }
            }

            $snapshotBuilder = new KioskFiscalSnapshotBuilder();
            foreach ($request->units as $unit) {
                $unitId = (int) $unit['kiosk_units_id'];
                if ($existingByUnitId->has($unitId)) {
                    $detail = $existingByUnitId->get($unitId);
                    $detail->price = $unit['price'];
                    $detail->save();
                    continue;
                }

                $unitModel = KioskUnit::with('product.tax')->find($unitId);
                if (!$unitModel) {
                    continue;
                }
                if ($unitModel->sold) {
                    // Ya vendida en otra factura
                    $ownedByThis = $kioskInvoice->details()->where('kiosk_units_id', $unitId)->exists();
                    if (!$ownedByThis) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "La unidad #{$unitId} ya está vendida.",
                            'errors' => ['units' => ["La unidad #{$unitId} no está disponible"]]
                        ], 422);
                    }
                }

                $snapshot = $snapshotBuilder->buildForUnit($unitModel, $unit['price']);
                KioskInvoiceDetail::create(array_merge([
                    'kiosk_invoices_id' => $kioskInvoice->id,
                    'kiosk_units_id' => $unitId,
                    'price' => $unit['price'],
                ], $snapshot));
                $unitModel->sold = true;
                $unitModel->save();
            }

            $kioskInvoice->load('details');
            $subtotal = (float) $kioskInvoice->details->sum('price');
            if ($kioskInvoice->coupon_code || (float) $kioskInvoice->manual_discount > 0) {
                $discount = $this->discountService->recalculateExisting(
                    $subtotal,
                    $kioskInvoice->coupon_code,
                    $kioskInvoice->manual_discount
                );
                $this->discountService->applyToInvoice(
                    $kioskInvoice,
                    $discount,
                    $kioskInvoice->manual_discount_by,
                    false
                );
            }
            $payable = $kioskInvoice->fresh()->payableTotal();

            if ($kioskInvoice->isRoomCredit()) {
                $creditPayment = $this->findCreditReservationPayment($kioskInvoice);
                if ($creditPayment) {
                    $creditPayment->amount = $payable;
                    $creditPayment->save();
                }
            }

            DB::commit();

            $kioskInvoice->load([
                'details.kiosk_unit.product.tax',
                'details.kiosk_unit.product.category',
                'payment_type',
                'customer',
                'reservation',
                'manualDiscountBy:id,name',
            ]);

            return response()->json($kioskInvoice, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sincronizando detalles de factura kiosko: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar los productos de la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cobrar una factura pendiente walk-in (pago real, no crédito).
     */
    public function pay(Request $request, KioskInvoice $kioskInvoice)
    {
        if (!$kioskInvoice->isPending()) {
            return response()->json([
                'message' => 'Solo se pueden cobrar facturas pendientes.',
            ], 422);
        }

        if ($kioskInvoice->isRoomCredit()) {
            return response()->json([
                'message' => 'Esta factura es cargo a habitación. Debe liquidarse en la reserva / checkout.',
                'errors' => ['payment_type_id' => ['Use el cobro por reserva para crédito a habitación']]
            ], 422);
        }

        $request->validate([
            'payment_type_id' => 'required|exists:payment_types,id',
            'payment_code' => 'required|string|max:50',
            'payed_value' => 'nullable|numeric',
            'electronic_invoice' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|max:50',
            'manual_discount' => 'nullable|numeric|min:0',
            'acquirer' => 'nullable|array',
        ]);

        $paymentType = PaymentType::find($request->payment_type_id);
        if ($paymentType->credit) {
            return response()->json([
                'message' => 'El cobro en caja requiere un método de pago real (no crédito).',
                'errors' => ['payment_type_id' => ['No se admite método a crédito en este endpoint']]
            ], 422);
        }

        DB::beginTransaction();
        try {
            $totalInvoice = $kioskInvoice->details()->sum('price');
            $discount = $this->discountService->resolve(
                $totalInvoice,
                $request->input('coupon_code'),
                $request->input('manual_discount')
            );
            $this->discountService->applyToInvoice($kioskInvoice, $discount, auth()->id(), true);
            $payable = $discount['payable'];

            $kioskInvoice->payment_type_id = $paymentType->id;
            $kioskInvoice->payment_code = $request->payment_code;
            $kioskInvoice->payed = true;
            if ($request->has('electronic_invoice')) {
                $kioskInvoice->electronic_invoice = $request->boolean('electronic_invoice');
            }
            if ($request->filled('payed_value') && $request->payed_value > 0) {
                $kioskInvoice->payed_value = $request->payed_value;
                $kioskInvoice->remain_money = $request->payed_value - $payable;
            }
            $kioskInvoice->save();

            $this->closureService->assignInvoice($kioskInvoice, $request->user()->id);
            if ($kioskInvoice->closure_id) {
                $this->closureService->refreshTotals($kioskInvoice->closure);
            }

            $electronicEnvelope = [];
            if ($this->electronicEmissionEnabled() && $kioskInvoice->electronic_invoice) {
                try {
                    $electronicEnvelope = $this->attemptElectronicEmission(
                        $kioskInvoice,
                        $request->input('acquirer')
                    );
                } catch (KioskEmissionInvalidPayloadException $ke) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $ke->getMessage(),
                        'errors' => ['acquirer' => [$ke->getMessage()]],
                        'electronic_document_error' => [
                            'code' => $ke->emissionCode(),
                            'message' => $ke->getMessage(),
                        ],
                    ], 422);
                }
            }

            DB::commit();

            $kioskInvoice->load([
                'details.kiosk_unit.product',
                'payment_type',
                'customer',
                'reservation',
                'manualDiscountBy:id,name',
            ]);

            $payload = $kioskInvoice->toArray();
            foreach ($electronicEnvelope as $key => $value) {
                $payload[$key] = $value;
            }
            return response()->json($payload, 200);
        } catch (ValidationException $ve) {
            DB::rollBack();
            $errors = $ve->errors();
            $firstMessage = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstMessage ?: 'Datos de factura inválidos.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cobrando factura kiosko: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al cobrar la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar factura pendiente: restaura unidades y la excluye del cierre.
     */
    public function cancel(Request $request, KioskInvoice $kioskInvoice)
    {
        if (!$kioskInvoice->isPending()) {
            return response()->json([
                'message' => 'Solo se pueden cancelar facturas pendientes.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $kioskInvoice->load('details');

            foreach ($kioskInvoice->details as $detail) {
                $unitModel = KioskUnit::find($detail->kiosk_units_id);
                if ($unitModel) {
                    $unitModel->sold = false;
                    $unitModel->save();
                }
            }

            $creditPayment = $this->findCreditReservationPayment($kioskInvoice);
            if ($creditPayment) {
                $creditPayment->delete();
            }

            $closureId = $kioskInvoice->closure_id;
            $kioskInvoice->cancelled_at = now();
            $kioskInvoice->closure_id = null;
            $kioskInvoice->save();

            if ($closureId) {
                $closure = \App\Models\CashRegisterClosure::find($closureId);
                if ($closure && !$closure->closed) {
                    $this->closureService->refreshTotals($closure);
                }
            }

            DB::commit();

            $kioskInvoice->load(['details', 'payment_type', 'customer', 'reservation', 'manualDiscountBy:id,name']);
            return response()->json([
                'message' => 'Factura cancelada. Las unidades fueron restauradas.',
                'invoice' => $kioskInvoice,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelando factura kiosko: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al cancelar la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    protected function findCreditReservationPayment(KioskInvoice $kioskInvoice): ?ReservationPayment
    {
        if (!$kioskInvoice->reservation_id) {
            return null;
        }

        return ReservationPayment::where('reservation_id', $kioskInvoice->reservation_id)
            ->where('concept', 'Compra en kiosko (a crédito)')
            ->where(function ($query) use ($kioskInvoice) {
                $query->where('notes', 'like', "%Factura kiosko #{$kioskInvoice->id}%")
                    ->orWhere('payment_reference', $kioskInvoice->payment_code);
            })
            ->first();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskInvoice $kioskInvoice)
    {
        if ($kioskInvoice->isPaid() || $kioskInvoice->isCancelled()) {
            return response()->json([
                'message' => 'No se puede editar una factura pagada o cancelada.',
            ], 422);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payed' => 'required|boolean',
            'payment_code' => 'required',
            'payment_type_id' => 'required|exists:payment_types,id',
        ]);

        $kioskInvoice->update($request->only([
            'customer_id',
            'payed',
            'payment_code',
            'payment_type_id',
            'payed_value',
            'remain_money',
            'electronic_invoice',
        ]));
        return response()->json($kioskInvoice, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskInvoice $kioskInvoice)
    {
        if ($kioskInvoice->isPaid() || $kioskInvoice->isCancelled()) {
            return response()->json([
                'message' => 'No se puede eliminar una factura pagada o cancelada. Use cancelar si está pendiente.',
            ], 422);
        }

        return $this->cancel(request(), $kioskInvoice);
    }
}
