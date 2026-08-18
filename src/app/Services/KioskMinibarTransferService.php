<?php

namespace App\Services;

use App\Models\KioskMinibarProductMap;
use App\Models\KioskProduct;
use App\Models\KioskUnit;
use App\Models\KioskUnitTransfer;
use App\Models\MinibarProduct;
use App\Models\MinibarWarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KioskMinibarTransferService
{
    /**
     * Trasladar unidades explícitas de kiosko hacia la bodega del minibar.
     *
     * @throws \InvalidArgumentException si alguna unidad no es trasladable o hay inconsistencias.
     */
    public function transfer(
        array $unitIds,
        int $minibarProductId,
        ?string $notes = null,
        ?int $userId = null,
        string $matchSource = 'manual'
    ): array {
        if (count($unitIds) !== count(array_unique($unitIds))) {
            throw new \InvalidArgumentException('Hay unidades duplicadas en la solicitud.');
        }

        $units = KioskUnit::whereIn('id', $unitIds)->get();

        if ($units->isEmpty()) {
            throw new \InvalidArgumentException('No se encontraron unidades para trasladar.');
        }

        $kioskProductIds = $units->pluck('product_id')->unique();
        if ($kioskProductIds->count() !== 1) {
            throw new \InvalidArgumentException('Todas las unidades deben pertenecer al mismo producto de kiosko.');
        }
        $kioskProductId = (int) $kioskProductIds->first();

        $this->assertMinibarProductExists($minibarProductId);
        $this->assertUnitsTransferable($units, $unitIds);

        return $this->executeTransfer($kioskProductId, $minibarProductId, $units, $notes, $userId, $matchSource);
    }

    /**
     * Trasladar una cantidad de unidades de un producto de kiosko, seleccionando
     * automáticamente las de vencimiento más próximo (FEFO).
     *
     * @throws \InvalidArgumentException si no hay suficientes unidades disponibles.
     */
    public function transferByQuantity(
        int $kioskProductId,
        int $quantity,
        int $minibarProductId,
        ?string $notes = null,
        ?int $userId = null,
        string $matchSource = 'manual'
    ): array {
        $this->assertMinibarProductExists($minibarProductId);

        $units = KioskUnit::query()
            ->where('product_id', $kioskProductId)
            ->available()
            ->orderByRaw('expiration IS NULL ASC')
            ->orderBy('expiration', 'asc')
            ->orderBy('id', 'asc')
            ->limit($quantity)
            ->get();

        if ($units->count() < $quantity) {
            throw new \InvalidArgumentException(
                "No hay suficientes unidades disponibles para trasladar. Disponibles: {$units->count()}, solicitadas: {$quantity}."
            );
        }

        return $this->executeTransfer($kioskProductId, $minibarProductId, $units, $notes, $userId, $matchSource);
    }

    /**
     * Crear o actualizar el mapeo persistido producto-kiosko ↔ producto-minibar.
     */
    public function ensureMapping(int $kioskProductId, int $minibarProductId, string $matchSource = 'manual'): KioskMinibarProductMap
    {
        return KioskMinibarProductMap::updateOrCreate(
            ['kiosk_product_id' => $kioskProductId],
            ['minibar_product_id' => $minibarProductId, 'match_source' => $matchSource]
        );
    }

    /**
     * Sugerir productos de minibar candidatos para un producto de kiosko,
     * ordenados por similitud (código/barras, nombre exacto o nombre contenido).
     */
    public function suggestions(int $kioskProductId): array
    {
        $kioskProduct = KioskProduct::find($kioskProductId);
        if (!$kioskProduct) {
            return [];
        }

        $kioskName = $this->normalize($kioskProduct->name);
        $kioskCode = $kioskProduct->code ? strtolower(trim($kioskProduct->code)) : null;

        return MinibarProduct::where('active', true)
            ->with('category')
            ->get()
            ->map(function (MinibarProduct $mp) use ($kioskName, $kioskCode) {
                $score = 0;
                $mpName = $this->normalize($mp->name);
                $mpBarcode = $mp->barcode ? strtolower(trim($mp->barcode)) : null;

                if ($kioskCode && $mpBarcode && $kioskCode === $mpBarcode) {
                    $score = 100;
                } elseif ($kioskName !== '' && $kioskName === $mpName) {
                    $score = 90;
                } elseif ($kioskName !== '' && $mpName !== '' && (
                    str_contains($kioskName, $mpName) || str_contains($mpName, $kioskName)
                )) {
                    $score = 70;
                }

                return [
                    'minibar_product' => $mp,
                    'score' => $score,
                ];
            })
            ->filter(fn ($c) => $c['score'] > 0)
            ->sortByDesc('score')
            ->values()
            ->map(fn ($c) => [
                'id' => $c['minibar_product']->id,
                'name' => $c['minibar_product']->name,
                'category' => $c['minibar_product']->category ? $c['minibar_product']->category->name : null,
                'score' => $c['score'],
            ])
            ->all();
    }

    private function assertMinibarProductExists(int $minibarProductId): void
    {
        if (!MinibarProduct::whereKey($minibarProductId)->exists()) {
            throw new \InvalidArgumentException('El producto de minibar destino no existe.');
        }
    }

    private function assertUnitsTransferable($units, array $requestedIds): void
    {
        $transferable = $units
            ->filter(function (KioskUnit $u) {
                if (!$u->active || $u->sold || $u->transferred_at !== null) {
                    return false;
                }
                if ($u->expiration !== null && $u->expiration->lessThan(now()->toDateString())) {
                    return false;
                }
                return true;
            })
            ->pluck('id')
            ->all();

        if (count($transferable) !== count($requestedIds)) {
            throw new \InvalidArgumentException(
                'Algunas unidades seleccionadas no están disponibles para trasladar (vendidas, inactivas, vencidas o ya trasladadas).'
            );
        }
    }

    private function executeTransfer(
        int $kioskProductId,
        int $minibarProductId,
        $units,
        ?string $notes,
        ?int $userId,
        string $matchSource
    ): array {
        $this->ensureMapping($kioskProductId, $minibarProductId, $matchSource);

        $batchId = (string) Str::uuid();
        $userId = $userId ?? auth()->id();
        $now = now();

        DB::beginTransaction();
        try {
            $stock = MinibarWarehouseStock::firstOrCreate(
                ['product_id' => $minibarProductId],
                ['current_quantity' => 0]
            );
            $stock->current_quantity += $units->count();
            $stock->save();

            $transferredIds = [];
            foreach ($units as $unit) {
                KioskUnitTransfer::create([
                    'transfer_batch_id' => $batchId,
                    'unit_id' => $unit->id,
                    'kiosk_product_id' => $kioskProductId,
                    'minibar_product_id' => $minibarProductId,
                    'expiration' => $unit->expiration,
                    'transferred_by' => $userId,
                    'notes' => $notes,
                    'transferred_at' => $now,
                ]);

                $unit->transferred_at = $now;
                $unit->save();
                $transferredIds[] = $unit->id;
            }

            DB::commit();

            return [
                'message' => 'Unidades trasladadas correctamente al minibar',
                'transfer_batch_id' => $batchId,
                'quantity' => $units->count(),
                'kiosk_product_id' => $kioskProductId,
                'minibar_product_id' => $minibarProductId,
                'unit_ids' => $transferredIds,
                'warehouse_quantity' => $stock->current_quantity,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}
