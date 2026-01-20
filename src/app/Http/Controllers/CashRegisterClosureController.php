<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterClosure;
use App\Models\KioskInvoice;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashRegisterClosureController extends Controller
{
    /**
     * Listar todos los cierres de caja
     */
    public function index(Request $request)
    {
        $query = CashRegisterClosure::with(['user', 'closedByUser', 'invoices'])
            ->orderBy('closure_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filtros opcionales
        if ($request->has('closed')) {
            $query->where('closed', $request->boolean('closed'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('closure_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('closure_date', '<=', $request->date_to);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json($query->get());
    }

    /**
     * Obtener un cierre específico
     */
    public function show(CashRegisterClosure $cashRegisterClosure)
    {
        $cashRegisterClosure->load([
            'user',
            'closedByUser',
            'invoices.customer',
            'invoices.payment_type',
            'invoices.details.kiosk_unit.product'
        ]);

        return response()->json($cashRegisterClosure);
    }

    /**
     * Obtener el cierre abierto del día actual para el usuario
     */
    public function getCurrentClosure(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        // Primero buscar un cierre abierto (prioridad: devolver el abierto si existe)
        $openClosure = CashRegisterClosure::where('user_id', $user->id)
            ->whereDate('closure_date', $today)
            ->where('closed', false)
            ->with(['invoices.customer', 'invoices.payment_type'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($openClosure) {
            // Si hay un cierre abierto, calcular totales y devolverlo
            $openClosure->calculateTotals();
            return response()->json($openClosure);
        }

        // Si no hay cierre abierto, buscar si existe uno cerrado para este día
        $closedClosure = CashRegisterClosure::where('user_id', $user->id)
            ->whereDate('closure_date', $today)
            ->where('closed', true)
            ->with(['invoices.customer', 'invoices.payment_type'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($closedClosure) {
            // Si ya existe un cierre cerrado para este día, devolverlo
            // No se puede crear un nuevo cierre el mismo día
            return response()->json($closedClosure);
        }

        // Solo crear un nuevo cierre si no existe NINGÚN cierre para este día
        $closure = CashRegisterClosure::create([
            'user_id' => $user->id,
            'closure_date' => $today,
            'opening_balance' => 0,
            'closed' => false
        ]);

        $closure->load(['invoices.customer', 'invoices.payment_type']);
        $closure->calculateTotals();

        return response()->json($closure);
    }

    /**
     * Crear un nuevo cierre de caja
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'closure_date' => 'required|date',
            'opening_balance' => 'required|numeric|min:0',
            'observations' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();

        // Verificar si ya existe un cierre (abierto o cerrado) para esta fecha
        $existingClosure = CashRegisterClosure::where('user_id', $user->id)
            ->whereDate('closure_date', $request->closure_date)
            ->first();

        if ($existingClosure) {
            $message = $existingClosure->closed 
                ? 'Ya existe un cierre cerrado para esta fecha. No se puede crear otro cierre para el mismo día.'
                : 'Ya existe un cierre abierto para esta fecha';
            
            return response()->json([
                'message' => $message,
                'closure' => $existingClosure
            ], 409);
        }

        $closure = CashRegisterClosure::create([
            'user_id' => $user->id,
            'closure_date' => $request->closure_date,
            'opening_balance' => $request->opening_balance,
            'observations' => $request->observations,
            'closed' => false
        ]);

        $closure->load('user');

        return response()->json($closure, 201);
    }

    /**
     * Actualizar un cierre de caja
     */
    public function update(Request $request, CashRegisterClosure $cashRegisterClosure)
    {
        if ($cashRegisterClosure->closed) {
            return response()->json([
                'message' => 'No se puede modificar un cierre de caja ya cerrado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'opening_balance' => 'sometimes|numeric|min:0',
            'observations' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $cashRegisterClosure->update($request->only([
            'opening_balance',
            'observations'
        ]));

        $cashRegisterClosure->load('user');

        return response()->json($cashRegisterClosure);
    }

    /**
     * Cerrar la caja (calcular totales y marcar como cerrada)
     */
    public function close(Request $request, CashRegisterClosure $cashRegisterClosure)
    {
        if ($cashRegisterClosure->closed) {
            return response()->json([
                'message' => 'Este cierre de caja ya está cerrado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'closing_balance' => 'required|numeric|min:0',
            'observations' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Asignar facturas sin cierre a este cierre (mismo día)
            $unassignedInvoices = KioskInvoice::whereNull('closure_id')
                ->whereDate('created_at', $cashRegisterClosure->closure_date)
                ->get();

            foreach ($unassignedInvoices as $invoice) {
                $invoice->closure_id = $cashRegisterClosure->id;
                $invoice->save();
            }

            // Calcular totales
            $cashRegisterClosure->calculateTotals();

            // Actualizar con el balance de cierre manual
            $cashRegisterClosure->closing_balance = $request->closing_balance;
            if ($request->has('observations')) {
                $cashRegisterClosure->observations = $request->observations;
            }

            // Marcar como cerrado
            $cashRegisterClosure->closed = true;
            $cashRegisterClosure->closed_by = $user->id;
            $cashRegisterClosure->closed_at = now();
            $cashRegisterClosure->save();

            DB::commit();

            $cashRegisterClosure->load(['user', 'closedByUser', 'invoices']);

            return response()->json([
                'message' => 'Cierre de caja realizado exitosamente',
                'closure' => $cashRegisterClosure
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al cerrar la caja',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte diario de caja
     */
    public function getDailyReport(Request $request, $date)
    {
        // Normalizar la fecha: extraer solo la parte de fecha (YYYY-MM-DD) si viene con timestamp
        try {
            $dateObj = Carbon::parse($date);
            $normalizedDate = $dateObj->format('Y-m-d');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Formato de fecha inválido',
                'error' => $e->getMessage()
            ], 422);
        }

        $validator = Validator::make(['date' => $normalizedDate], [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = $request->user();
        $closure = CashRegisterClosure::where('user_id', $user->id)
            ->whereDate('closure_date', $normalizedDate)
            ->with(['invoices.customer', 'invoices.payment_type', 'invoices.details'])
            ->first();

        if (!$closure) {
            return response()->json([
                'message' => 'No se encontró cierre de caja para esta fecha'
            ], 404);
        }

        // Calcular totales si no está cerrado
        if (!$closure->closed) {
            $closure->calculateTotals();
        }

        return response()->json($closure);
    }

    /**
     * Eliminar un cierre de caja (solo si está abierto)
     */
    public function destroy(CashRegisterClosure $cashRegisterClosure)
    {
        if ($cashRegisterClosure->closed) {
            return response()->json([
                'message' => 'No se puede eliminar un cierre de caja cerrado'
            ], 403);
        }

        // Desasignar facturas
        KioskInvoice::where('closure_id', $cashRegisterClosure->id)
            ->update(['closure_id' => null]);

        $cashRegisterClosure->delete();

        return response()->json(null, 204);
    }
}

