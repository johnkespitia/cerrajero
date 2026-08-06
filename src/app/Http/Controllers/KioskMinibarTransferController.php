<?php

namespace App\Http\Controllers;

use App\Models\KioskMinibarProductMap;
use App\Models\MinibarProduct;
use App\Services\KioskMinibarTransferService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class KioskMinibarTransferController extends Controller
{
    protected $transferService;

    public function __construct(KioskMinibarTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Trasladar unidades de kiosko hacia la bodega del minibar.
     * Acepta unit_ids explícitos o kiosk_product_id + quantity (FEFO).
     */
    public function transfer(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'unit_ids' => 'required_without:kiosk_product_id|array|min:1',
            'unit_ids.*' => 'required|exists:kiosk_units,id',
            'kiosk_product_id' => 'required_without:unit_ids|exists:kiosk_products,id',
            'quantity' => 'required_with:kiosk_product_id|integer|min:1',
            'minibar_product_id' => 'required|exists:minibar_products,id',
            'notes' => 'nullable|string|max:500',
            'match_source' => 'sometimes|in:auto,manual',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if ($request->has('unit_ids')) {
                $result = $this->transferService->transfer(
                    $request->unit_ids,
                    (int) $request->minibar_product_id,
                    $request->notes,
                    auth()->id(),
                    $request->match_source ?? 'manual'
                );
            } else {
                $result = $this->transferService->transferByQuantity(
                    (int) $request->kiosk_product_id,
                    (int) $request->quantity,
                    (int) $request->minibar_product_id,
                    $request->notes,
                    auth()->id(),
                    $request->match_source ?? 'manual'
                );
            }

            return response()->json($result, Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al trasladar unidades',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear o actualizar el mapeo producto-kiosko ↔ producto-minibar.
     */
    public function setMapping(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'kiosk_product_id' => 'required|exists:kiosk_products,id',
            'minibar_product_id' => 'required|exists:minibar_products,id',
            'match_source' => 'sometimes|in:auto,manual',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $map = $this->transferService->ensureMapping(
            (int) $request->kiosk_product_id,
            (int) $request->minibar_product_id,
            $request->match_source ?? 'manual'
        );

        return response()->json([
            'message' => 'Mapeo guardado correctamente',
            'map' => $map,
        ], Response::HTTP_OK);
    }

    /**
     * Sugerir candidatos de productos minibar para un producto de kiosko.
     */
    public function suggestions(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'kiosk_product_id' => 'required|exists:kiosk_products,id',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validation->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(
            $this->transferService->suggestions((int) $request->kiosk_product_id),
            Response::HTTP_OK
        );
    }

    /**
     * Listar los mapeos persistidos con sus productos.
     */
    public function mappings()
    {
        $maps = KioskMinibarProductMap::with(['kioskProduct', 'minibarProduct'])->get();

        return response()->json($maps, Response::HTTP_OK);
    }

    /**
     * Catálogo de productos minibar activos disponibles como destino de traslados.
     */
    public function minibarProducts()
    {
        $products = MinibarProduct::where('active', true)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(fn (MinibarProduct $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category ? $p->category->name : null,
            ]);

        return response()->json($products, Response::HTTP_OK);
    }
}
