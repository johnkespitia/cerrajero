<?php

namespace App\Http\Controllers;

use App\Models\RoomInventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomInventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomInventoryItem::with(['category', 'activeAssignments.assignable']);

        // Filtros
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('qr_code', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('name')->get();

        return response($items, Response::HTTP_OK);
    }

    public function lookup(Request $request, \App\Services\RoomInventoryAuditService $auditService)
    {
        $request->validate([
            'qr' => 'required|string|max:500',
        ]);

        $value = trim((string) $request->query('qr'));

        $item = $this->resolveByQr($value);

        if (! $item) {
            return response([
                'message' => 'No se encontró ningún artículo con ese código QR.',
            ], Response::HTTP_NOT_FOUND);
        }

        $auditService->log('qr_scanned', [
            'item_id' => $item->id,
            'assignable_type' => null,
            'assignable_id' => null,
            'notes' => 'Búsqueda por QR' . ($value !== $item->qr_code ? " (valor decodificado: {$value})" : ''),
        ], null, $request);

        $item->load(['category', 'activeAssignments.assignable']);

        return response($item, Response::HTTP_OK);
    }

    protected function resolveByQr(string $value): ?RoomInventoryItem
    {
        $item = RoomInventoryItem::where('qr_code', $value)->first();
        if ($item) {
            return $item;
        }

        if (preg_match('#/room-inventory/items/(\d+)\b#', $value, $matches)) {
            return RoomInventoryItem::find((int) $matches[1]);
        }

        if (ctype_digit($value)) {
            return RoomInventoryItem::find((int) $value);
        }

        return null;
    }

    public function storeBatch(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'serial_prefix' => 'nullable|string|max:100',
            'barcode_prefix' => 'nullable|string|max:100',
            'base.name' => 'required|string|max:250',
            'base.description' => 'nullable|string',
            'base.category_id' => 'nullable|exists:room_inventory_categories,id',
            'base.brand' => 'nullable|string|max:125',
            'base.model' => 'nullable|string|max:125',
            'base.purchase_price' => 'nullable|numeric|min:0',
            'base.current_value' => 'nullable|numeric|min:0',
            'base.purchase_date' => 'nullable|date',
            'base.warranty_expires_at' => 'nullable|date',
            'base.image_url' => 'nullable|string|max:500',
            'base.active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $base = $request->input('base');
        $quantity = (int) $request->input('quantity');
        $serialPrefix = $request->input('serial_prefix');
        $barcodePrefix = $request->input('barcode_prefix');

        $items = [];
        $chunkSize = 100;
        $chunks = (int) ceil($quantity / $chunkSize);

        for ($c = 0; $c < $chunks; $c++) {
            $start = $c * $chunkSize + 1;
            $end = min(($c + 1) * $chunkSize, $quantity);
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                for ($i = $start; $i <= $end; $i++) {
                    $name = $quantity > 1 ? $base['name'] . ' #' . $i : $base['name'];
                    $serial = null;
                    $barcode = null;
                    if ($serialPrefix !== null && $serialPrefix !== '') {
                        $serial = $serialPrefix . '-' . $i;
                    }
                    if ($barcodePrefix !== null && $barcodePrefix !== '') {
                        $barcode = $barcodePrefix . '-' . $i;
                    }
                    $item = RoomInventoryItem::create([
                        'name' => $name,
                        'description' => $base['description'] ?? null,
                        'category_id' => $base['category_id'] ?? null,
                        'brand' => $base['brand'] ?? null,
                        'model' => $base['model'] ?? null,
                        'serial_number' => $serial,
                        'barcode' => $barcode,
                        'purchase_price' => $base['purchase_price'] ?? null,
                        'current_value' => $base['current_value'] ?? null,
                        'purchase_date' => $base['purchase_date'] ?? null,
                        'warranty_expires_at' => $base['warranty_expires_at'] ?? null,
                        'image_url' => $base['image_url'] ?? null,
                        'active' => $base['active'] ?? true,
                    ]);
                    $item->load('category');
                    $items[] = $item;
                }
                \Illuminate\Support\Facades\DB::commit();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response(['message' => 'Error en lote: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response(['message' => $quantity . ' artículo(s) creado(s) exitosamente', 'count' => $quantity, 'items' => $items], Response::HTTP_CREATED);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:room_inventory_categories,id',
            'brand' => 'nullable|string|max:125',
            'model' => 'nullable|string|max:125',
            'serial_number' => 'nullable|string|max:125|unique:room_inventory_items,serial_number',
            'barcode' => 'nullable|string|max:125|unique:room_inventory_items,barcode',
            'qr_code' => 'nullable|string|max:500|unique:room_inventory_items,qr_code',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expires_at' => 'nullable|date',
            'image_url' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = RoomInventoryItem::create($request->all());
        $item->load('category');

        return response(['message' => 'Artículo creado exitosamente', 'item' => $item], Response::HTTP_CREATED);
    }

    public function show(RoomInventoryItem $roomInventoryItem)
    {
        $roomInventoryItem->load(['category', 'assignments.assignable', 'history.user']);
        return response($roomInventoryItem, Response::HTTP_OK);
    }

    public function update(Request $request, RoomInventoryItem $roomInventoryItem)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:250',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:room_inventory_categories,id',
            'brand' => 'nullable|string|max:125',
            'model' => 'nullable|string|max:125',
            'serial_number' => 'nullable|string|max:125|unique:room_inventory_items,serial_number,' . $roomInventoryItem->id,
            'barcode' => 'nullable|string|max:125|unique:room_inventory_items,barcode,' . $roomInventoryItem->id,
            'qr_code' => 'nullable|string|max:500|unique:room_inventory_items,qr_code,' . $roomInventoryItem->id,
            'purchase_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expires_at' => 'nullable|date',
            'image_url' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);

        if ($validation->fails()) {
            return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryItem->update($request->all());
        $roomInventoryItem->load('category');

        return response(['message' => 'Artículo actualizado exitosamente', 'item' => $roomInventoryItem], Response::HTTP_OK);
    }

    public function updateBatch(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:room_inventory_items,id',
            'updates.name' => 'nullable|string|max:250',
            'updates.description' => 'nullable|string',
            'updates.category_id' => 'nullable|exists:room_inventory_categories,id',
            'updates.brand' => 'nullable|string|max:125',
            'updates.model' => 'nullable|string|max:125',
            'updates.purchase_price' => 'nullable|numeric|min:0',
            'updates.current_value' => 'nullable|numeric|min:0',
            'updates.purchase_date' => 'nullable|date',
            'updates.warranty_expires_at' => 'nullable|date',
            'updates.image_url' => 'nullable|string|max:500',
            'updates.active' => 'nullable|boolean',
        ]);
        if ($validation->fails()) return response($validation->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $updates = array_filter($request->input('updates', []), fn($v) => $v !== null && $v !== '');
        if (empty($updates)) return response(['message' => 'Nada para actualizar'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // If updating name, preserve suffix #N
        $isNameUpdate = array_key_exists('name', $updates) && $updates['name'] !== null && $updates['name'] !== '';
        $newBaseName = $isNameUpdate ? trim((string) $updates['name']) : null;

        $ids = $request->input('ids');
        $updated = 0;
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $item = RoomInventoryItem::find($id);
                if (!$item) continue;
                $data = $updates;
                if ($isNameUpdate) {
                    // Preserve suffix #N if original had it
                    if (preg_match('/\s+#\d+$/', $item->name, $m)) {
                        $data['name'] = $newBaseName . $m[0];
                    } else {
                        // If original was single without suffix but batch typo case, just use new base
                        // If ids count >1, add suffix handling outside? For simplicity, keep base for first, suffix for others based on index
                        $data['name'] = $newBaseName;
                    }
                }
                $item->update($data);
                $updated++;
            }
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response(['message' => 'Error en actualización masiva: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // For bulk typo like "Mesa de nadera" batch, need to handle suffix preservation for all if original batch had suffix
        if ($isNameUpdate && count($ids) > 1) {
            // Re-apply suffix correctly for batch groups: fetch updated items to re-suffix sequentially if they were batch
            $items = RoomInventoryItem::whereIn('id', $ids)->orderBy('id')->get();
            $hasSuffix = $items->filter(fn($it) => preg_match('/\s+#\d+$/', $it->name))->count() > 0;
            if (!$hasSuffix) {
                // If original batch had suffix but we overwrote without suffix (single base), fix to add #1..N
                // Detect by checking if any name equals base exactly and count>1
                $allSame = $items->pluck('name')->unique()->count() === 1;
                if ($allSame) {
                    foreach ($items as $idx => $it) {
                        $it->update(['name' => $newBaseName . ' #' . ($idx + 1)]);
                    }
                }
            }
        }

        return response(['message' => $updated . ' artículo(s) actualizado(s)', 'updated' => $updated], Response::HTTP_OK);
    }

    public function destroy(RoomInventoryItem $roomInventoryItem)
    {
        // Verificar si tiene asignaciones activas
        if ($roomInventoryItem->activeAssignments()->count() > 0) {
            return response(['message' => 'No se puede eliminar el artículo porque tiene asignaciones activas'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $roomInventoryItem->delete();

        return response(['message' => 'Artículo eliminado exitosamente'], Response::HTTP_OK);
    }
}
