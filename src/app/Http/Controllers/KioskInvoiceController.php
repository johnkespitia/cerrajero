<?php

namespace App\Http\Controllers;

use App\Models\KioskInvoice;
use App\Models\PaymentType;
use App\Models\KioskUnit;
use App\Models\KioskInvoiceDetail;
use App\Models\CashRegisterClosure;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Services\KioskOtpService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class KioskInvoiceController extends Controller
{
    protected $otpService;

    public function __construct(KioskOtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskInvoice::with(["customer","payment_type","details.kiosk_unit.product.tax","details.kiosk_unit.product.category"])
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
            $units = $request->get("units");
            foreach ($units as $unit) {
                KioskInvoiceDetail::create([
                    'kiosk_invoices_id' => $tempInvoice->id,
                    'kiosk_units_id' => $unit['kiosk_units_id'],
                    'price' => $unit['price']
                ]);
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

            // Actualizar factura
            if ($request->has('payed_value') && $request->payed_value > 0) {
                $kioskInvoice->payed_value = $request->payed_value;
                $kioskInvoice->remain_money = $request->payed_value - $total_invoice;
            }
            $kioskInvoice->save();

            // Crear pago en reserva (si aplica)
            if ($kioskInvoice->reservation_id && $kioskInvoice->payment_type->credit) {
                ReservationPayment::create([
                    'reservation_id' => $kioskInvoice->reservation_id,
                    'amount' => $total_invoice,
                    'concept' => 'Compra en kiosko (a crédito)',
                    'payment_type_id' => $kioskInvoice->payment_type_id,
                    'payment_reference' => $kioskInvoice->payment_code,
                    'notes' => "Factura kiosko #{$kioskInvoice->id} - Pendiente de pago",
                    'created_by' => auth()->id(),
                ]);
            }

            // Asignar a cierre de caja
            $user = $request->user();
            $today = Carbon::today();
            $closure = CashRegisterClosure::where('user_id', $user->id)
                ->whereDate('closure_date', $today)
                ->where('closed', false)
                ->first();
            if ($closure) {
                $kioskInvoice->closure_id = $closure->id;
                $kioskInvoice->save();
            }

            DB::commit();

            $kioskInvoice->load(['details.kiosk_unit.product', 'payment_type', 'customer', 'reservation']);
            
            return response()->json([
                'message' => 'Compra completada exitosamente.',
                'invoice' => $kioskInvoice
            ], 200);
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
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'reservation_id' => 'nullable|exists:reservations,id', // Permitir especificar reserva
                'payment_code' => 'required',
                'payment_type_id' => 'required|exists:payment_types,id',
                'units'=> 'required|array',
                'units.*.kiosk_units_id' => 'required|exists:kiosk_units,id',
                'units.*.price' => 'required|numeric|min:0',
                'electronic_invoice' => 'required|boolean',
                'payed_value' => 'numeric',
                'temp_invoice_id' => 'nullable|exists:kiosk_invoices,id', // ID de factura temporal con OTP
                'otp_code' => 'nullable|string|size:6' // OTP para compras a crédito
            ]);

            // OPCIÓN C: Asociación Automática Inteligente de Reservas
            $activeReservation = $this->findActiveReservation($request->customer_id, $request->reservation_id);
            
            if ($request->has('reservation_id') && $request->reservation_id && !$activeReservation) {
                DB::rollBack();
                return response()->json([
                    'message' => 'La reserva especificada no existe, no pertenece al cliente o no está en estado checked_in.',
                    'errors' => ['reservation_id' => ['Reserva inválida o no disponible']]
                ], 422);
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
                $requestBody = $request->all();
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
                foreach ($units as $key => $unit) {
                    $unitModel = KioskUnit::find($unit['kiosk_units_id']);
                    $unit['kiosk_invoices_id'] = $kioskInvoice->id;
                    $unit_saved = KioskInvoiceDetail::create($unit);
                    $unitModel->sold = true;
                    $unitModel->save();
                    $total_invoice += $unitModel->product->sale_price;
                }
            }
            if($request->get('payed_value') > 0){
                $kioskInvoice->remain_money = $kioskInvoice->payed_value - $total_invoice;
                $kioskInvoice->save();
            }

            // REGLA 2 y 3: Si hay reserva activa, crear pago en la reserva
            if ($activeReservation) {
                if ($paymentType->credit) {
                    // REGLA 2: credit = 1 → crear pago PENDIENTE en reserva
                    ReservationPayment::create([
                        'reservation_id' => $activeReservation->id,
                        'amount' => $total_invoice,
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
                        'amount' => $total_invoice,
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
                        ->with('details')
                        ->get();

                    $totalPendingKiosk = $pendingKioskInvoices->sum(function ($invoice) {
                        return $invoice->details->sum('price');
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

            // Asignar a cierre de caja abierto del día
            $user = $request->user();
            $today = Carbon::today();

            $closure = CashRegisterClosure::where('user_id', $user->id)
                ->whereDate('closure_date', $today)
                ->where('closed', false)
                ->first();

            if ($closure) {
                $kioskInvoice->closure_id = $closure->id;
                $kioskInvoice->save();
            }

            DB::commit();

            $kioskInvoice->load(['details', 'payment_type', 'customer', 'reservation']);
            return response()->json($kioskInvoice, 201);
        }catch(ValidationException $ve){
            DB::rollBack();
            return response()->json([
                'errors' => $ve->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear factura de kiosko: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear la factura',
                'error' => $e->getMessage()
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(KioskInvoice $kioskInvoice)
    {
        return $kioskInvoice;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskInvoice $kioskInvoice)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payed' => 'required|boolean',
            'payment_code' => 'required',
            'payment_type_id' => 'required|exists:payment_types,id',
        ]);

        $kioskInvoice->update($request->all());
        return response()->json($kioskInvoice, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskInvoice $kioskInvoice)
    {
        $kioskInvoice->delete();
        return response()->json(null, 204);
    }
}
