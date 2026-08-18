<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\ReservationAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationClientTransferService
{
    /**
     * Transferencia controlada de cliente en una reserva.
     *
     * Guarda el cliente anterior, actualiza el cliente nuevo y crea un registro de auditoría.
     *
     * @param int $reservationId ID de la reserva
     * @param int $newCustomerId ID del nuevo cliente
     * @param string $reason Razón del traslado
     * @param int $userId ID del usuario que realiza el cambio
     * @return array Resultado de la operación
     */
    public function transferClient(int $reservationId, int $newCustomerId, string $reason, int $userId): array
    {
        $reservation = Reservation::with('customer')->findOrFail($reservationId);
        $newCustomer = Customer::findOrFail($newCustomerId);

        $oldCustomerId = $reservation->customer_id;
        $oldCustomer = $oldCustomerId ? Customer::find($oldCustomerId) : null;

        DB::beginTransaction();
        try {
            // Guardar valores antiguos y nuevos para auditoría
            $oldValues = $oldCustomer ? [
                'id' => $oldCustomer->id,
                'name' => $oldCustomer->name ?? null,
                'last_name' => $oldCustomer->last_name ?? null,
                'email' => $oldCustomer->email ?? null,
                'dni' => $oldCustomer->dni ?? null,
                'customer_type' => $oldCustomer->customer_type ?? null,
                'company_name' => $oldCustomer->company_name ?? null,
                'company_nit' => $oldCustomer->company_nit ?? null,
            ] : null;

            $newValues = [
                'id' => $newCustomer->id,
                'name' => $newCustomer->name ?? null,
                'last_name' => $newCustomer->last_name ?? null,
                'email' => $newCustomer->email ?? null,
                'dni' => $newCustomer->dni ?? null,
                'customer_type' => $newCustomer->customer_type ?? null,
                'company_name' => $newCustomer->company_name ?? null,
                'company_nit' => $newCustomer->company_nit ?? null,
            ];

            // Actualizar la reserva con el nuevo cliente
            $reservation->update([
                'customer_id' => $newCustomerId,
                'updated_at' => now(),
            ]);

            // Crear registro de auditoría
            $audit = ReservationAudit::create([
                'reservation_id' => $reservationId,
                'user_id' => $userId,
                'action' => 'client_transfer',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'notes' => $reason,
                'ip_address' => request()->ip() ?? null,
                'user_agent' => request()->header('User-Agent') ?? null,
            ]);

            DB::commit();

            return [
                'success' => true,
                'reservation_id' => $reservationId,
                'old_customer_id' => $oldCustomerId,
                'new_customer_id' => $newCustomerId,
                'audit_id' => $audit->id,
                'message' => 'Transferencia de cliente completada correctamente',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en transferencia de cliente de reserva', [
                'reservation_id' => $reservationId,
                'new_customer_id' => $newCustomerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al procesar la transferencia de cliente',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Obtener historial de transferencias de cliente de una reserva.
     *
     * @param int $reservationId
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getClientTransferHistory(int $reservationId): \Illuminate\Database\Eloquent\Collection
    {
        return ReservationAudit::where('reservation_id', $reservationId)
            ->where('action', 'client_transfer')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}