<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\MinibarProduct;
use App\Models\RoomMinibarInventory;
use App\Models\RoomMinibarStock;
use App\Models\ReservationMinibarCharge;
use App\Models\MinibarRestockingLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MinibarInventoryService
{
    /**
     * Obtener inventario base de una habitación
     */
    public function getRoomStock(Room $room): array
    {
        return RoomMinibarStock::where('room_id', $room->id)
            ->where('active', true)
            ->with('product')
            ->get()
            ->toArray();
    }

    /**
     * Registrar reposición de productos
     */
    public function restockProducts(
        Room $room,
        array $products, // [product_id => quantity_added]
        string $reason = 'standard',
        ?int $userId = null
    ): array {
        $logs = [];

        DB::beginTransaction();
        try {
            foreach ($products as $productId => $quantityAdded) {
                $stock = RoomMinibarStock::firstOrCreate(
                    [
                        'room_id' => $room->id,
                        'product_id' => $productId,
                    ],
                    [
                        'standard_quantity' => 0,
                        'current_quantity' => 0,
                        'active' => true,
                    ]
                );

                $quantityBefore = $stock->current_quantity;
                $stock->current_quantity += $quantityAdded;
                $stock->last_restocked_at = now();
                $stock->last_restocked_by = $userId ?? auth()->id();
                $stock->save();

                $log = MinibarRestockingLog::create([
                    'room_id' => $room->id,
                    'product_id' => $productId,
                    'quantity_added' => $quantityAdded,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $stock->current_quantity,
                    'restocked_at' => now(),
                    'restocked_by' => $userId ?? auth()->id(),
                    'reason' => $reason,
                ]);

                $logs[] = $log;
            }

            DB::commit();
            return $logs;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Registrar inventario inicial al check-in
     */
    public function recordCheckInInventory(
        Reservation $reservation,
        array $products = [], // [product_id => quantity] o vacío para usar stock actual
        ?int $userId = null
    ): array {
        $records = [];

        DB::beginTransaction();
        try {
            // Si no se proporcionan productos, usar el stock actual de la habitación
            if (empty($products)) {
                $stockItems = RoomMinibarStock::where('room_id', $reservation->room_id)
                    ->where('active', true)
                    ->get();

                foreach ($stockItems as $stock) {
                    $products[$stock->product_id] = $stock->current_quantity;
                }
            }

            foreach ($products as $productId => $quantity) {
                $product = MinibarProduct::findOrFail($productId);

                $record = RoomMinibarInventory::create([
                    'reservation_id' => $reservation->id,
                    'room_id' => $reservation->room_id,
                    'product_id' => $productId,
                    'initial_quantity' => $quantity,
                    'current_quantity' => $quantity,
                    'consumed_quantity' => 0,
                    'record_type' => 'check_in',
                    'recorded_by' => $userId ?? auth()->id(),
                    'recorded_at' => now(),
                ]);

                // Actualizar stock actual de la habitación
                $stock = RoomMinibarStock::firstOrCreate(
                    [
                        'room_id' => $reservation->room_id,
                        'product_id' => $productId,
                    ],
                    [
                        'standard_quantity' => $quantity,
                        'current_quantity' => $quantity,
                        'active' => true,
                    ]
                );

                $stock->current_quantity = $quantity;
                $stock->save();

                $records[] = $record;
            }

            DB::commit();
            return $records;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Registrar inventario durante limpieza o checkout
     */
    public function recordInventoryUpdate(
        Reservation $reservation,
        array $products, // [product_id => current_quantity]
        string $recordType, // 'cleaning' o 'check_out'
        ?int $userId = null
    ): array {
        $records = [];
        $charges = [];

        DB::beginTransaction();
        try {
            foreach ($products as $productId => $currentQuantity) {
                // Buscar registro inicial (check-in)
                $initialRecord = RoomMinibarInventory::where('reservation_id', $reservation->id)
                    ->where('product_id', $productId)
                    ->where('record_type', 'check_in')
                    ->first();

                if (!$initialRecord) {
                    // Si no hay registro inicial, crear uno (por si se agregó producto después)
                    $initialRecord = RoomMinibarInventory::create([
                        'reservation_id' => $reservation->id,
                        'room_id' => $reservation->room_id,
                        'product_id' => $productId,
                        'initial_quantity' => 0,
                        'current_quantity' => $currentQuantity,
                        'consumed_quantity' => 0,
                        'record_type' => 'check_in',
                        'recorded_by' => $userId ?? auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                // Buscar el registro más reciente (puede ser check-in o limpieza previa)
                $latestRecord = RoomMinibarInventory::where('reservation_id', $reservation->id)
                    ->where('product_id', $productId)
                    ->orderBy('recorded_at', 'desc')
                    ->first();

                // Calcular consumo: diferencia entre la cantidad más reciente y la actual
                // Si es la primera limpieza, comparar con check-in; si hay limpiezas previas, comparar con la última
                $previousQuantity = $latestRecord ? $latestRecord->current_quantity : $initialRecord->initial_quantity;
                $consumed = max(0, $previousQuantity - $currentQuantity);

                $updateRecord = RoomMinibarInventory::create([
                    'reservation_id' => $reservation->id,
                    'room_id' => $reservation->room_id,
                    'product_id' => $productId,
                    'initial_quantity' => $initialRecord->initial_quantity,
                    'current_quantity' => $currentQuantity,
                    'consumed_quantity' => $consumed,
                    'record_type' => $recordType,
                    'recorded_by' => $userId ?? auth()->id(),
                    'recorded_at' => now(),
                ]);

                $records[] = $updateRecord;

                // Si es producto vendible y hay consumo, crear cargo
                $product = MinibarProduct::findOrFail($productId);
                
                // Log para debugging
                Log::info('Minibar charge check', [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'is_sellable' => $product->is_sellable,
                    'consumed' => $consumed,
                    'previous_quantity' => $previousQuantity,
                    'current_quantity' => $currentQuantity,
                    'record_type' => $recordType,
                    'reservation_id' => $reservation->id
                ]);
                
                if ($product->is_sellable && $consumed > 0) {
                    // Verificar si ya existe un cargo para este producto en esta reserva
                    $existingCharge = ReservationMinibarCharge::where('reservation_id', $reservation->id)
                        ->where('product_id', $productId)
                        ->where('record_type', $recordType)
                        ->first();

                    if ($existingCharge) {
                        // Actualizar cargo existente
                        $existingCharge->update([
                            'quantity' => $consumed,
                            'total' => round($consumed * $product->sale_price, 2),
                        ]);
                        $charges[] = $existingCharge;
                    } else {
                        // Crear nuevo cargo
                        $charge = ReservationMinibarCharge::create([
                            'reservation_id' => $reservation->id,
                            'inventory_record_id' => $updateRecord->id,
                            'product_id' => $productId,
                            'quantity' => $consumed,
                            'unit_price' => $product->sale_price,
                            'total' => round($consumed * $product->sale_price, 2),
                            'record_type' => $recordType,
                            'recorded_by' => $userId ?? auth()->id(),
                            'recorded_at' => now(),
                        ]);
                        $charges[] = $charge;
                    }
                }

                // Si es checkout, actualizar stock de la habitación
                if ($recordType === 'check_out') {
                    $stock = RoomMinibarStock::firstOrCreate(
                        [
                            'room_id' => $reservation->room_id,
                            'product_id' => $productId,
                        ],
                        [
                            'standard_quantity' => 0,
                            'current_quantity' => 0,
                            'active' => true,
                        ]
                    );

                    $stock->current_quantity = $currentQuantity;
                    $stock->save();
                }
            }

            // Recalcular precio final de la reserva
            $reservation->recomputeFinalPrice();

            DB::commit();
            return [
                'records' => $records,
                'charges' => $charges,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener inventario actual de una reserva
     */
    public function getReservationInventory(Reservation $reservation): array
    {
        // Obtener TODOS los registros de inventario (check-in, limpieza, check-out)
        // ordenados por producto y fecha para mostrar el historial completo
        $allRecords = RoomMinibarInventory::where('reservation_id', $reservation->id)
            ->with('product')
            ->orderBy('product_id')
            ->orderBy('recorded_at', 'desc')
            ->get();

        // Convertir a array para incluir las relaciones
        return array_map(function($record) {
            return $record->toArray();
        }, $allRecords->all());
    }

    /**
     * Obtener cargos del minibar de una reserva
     */
    public function getReservationCharges(Reservation $reservation): array
    {
        return ReservationMinibarCharge::where('reservation_id', $reservation->id)
            ->with('product')
            ->get()
            ->toArray();
    }

    /**
     * Obtener productos que necesitan reposición en una habitación
     */
    public function getProductsNeedingRestock(Room $room): array
    {
        return RoomMinibarStock::where('room_id', $room->id)
            ->where('active', true)
            ->whereColumn('current_quantity', '<', 'standard_quantity')
            ->with('product')
            ->get()
            ->toArray();
    }
}
