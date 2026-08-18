<?php

namespace App\Http\Controllers\Api\RoomInventory;

use App\Http\Controllers\Controller;
use App\Models\RoomInventoryItem;
use App\Services\RoomInventoryAuditService;
use BaconQrCode\Renderer\Image\Png\PngImageRenderer;
use BaconQrCode\Renderer\Image\Svg\SvgImageRenderer;
use BaconQrCode\Renderer\RendererStyle\BasicStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function __construct(private readonly RoomInventoryAuditService $auditService)
    {
    }

    public function generate(RoomInventoryItem $roomInventoryItem): JsonResponse
    {
        $payload = $this->resolvePayload($roomInventoryItem);

        $writer = new Writer(new SvgImageRenderer(new BasicStyle(300, 300)));
        $svg = $writer->write($payload, 3);

        return response()->json([
            'id' => $roomInventoryItem->id,
            'name' => $roomInventoryItem->name,
            'qr_code' => $svg,
            'url' => $payload,
        ]);
    }

    public function downloadSvg(RoomInventoryItem $roomInventoryItem): Response
    {
        $payload = $this->resolvePayload($roomInventoryItem);

        $writer = new Writer(new SvgImageRenderer(new BasicStyle(300, 300)));
        $svg = $writer->write($payload, 3);

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $roomInventoryItem->id . '.svg"');
    }

    public function downloadPng(RoomInventoryItem $roomInventoryItem): Response
    {
        $payload = $this->resolvePayload($roomInventoryItem);

        $writer = new Writer(new PngImageRenderer());
        $png = $writer->write($payload, 3);

        return response($png, 200)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $roomInventoryItem->id . '.png"');
    }

    public function regenerate(Request $request, RoomInventoryItem $roomInventoryItem): JsonResponse
    {
        $previous = $roomInventoryItem->qr_code;
        $roomInventoryItem->update(['qr_code' => (string) Str::uuid()]);
        $roomInventoryItem->refresh();

        $this->auditService->log('qr_regenerated', [
            'item_id' => $roomInventoryItem->id,
            'assignable_type' => null,
            'assignable_id' => null,
            'notes' => 'QR regenerado' . ($previous ? " (anterior: {$previous})" : ''),
        ], null, $request);

        return response()->json([
            'message' => 'QR regenerado exitosamente',
            'item' => $roomInventoryItem,
        ]);
    }

    public function printSheet(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $idsParam = $request->query('ids', '');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $idsParam))));

        if (empty($ids)) {
            return response([
                'message' => 'Debes indicar al menos un id de artículo.',
                'errors' => ['ids' => ['El parámetro ids es obligatorio y debe contener al menos un id.']],
            ], 422);
        }

        if (count($ids) > 240) {
            return response([
                'message' => 'Máximo 240 artículos por hoja (10 páginas × 24 etiquetas).',
                'errors' => ['ids' => ['Excede el máximo permitido.']],
            ], 422);
        }

        $items = RoomInventoryItem::with('category')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            return response([
                'message' => 'Ninguno de los ids indicados existe.',
            ], 404);
        }

        $qrWriter = new Writer(new SvgImageRenderer(new BasicStyle(150, 150)));

        $labels = $items->map(function (RoomInventoryItem $item) use ($qrWriter) {
            $payload = $this->resolvePayload($item);
            return [
                'id' => $item->id,
                'name' => $item->name,
                'qr_code' => $item->qr_code,
                'barcode' => $item->barcode,
                'category' => $item->category?->name,
                'svg' => $qrWriter->write($payload, 2),
            ];
        })->values()->all();

        return response()
            ->view('room_inventory.print_sheet', ['labels' => $labels])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    protected function resolvePayload(RoomInventoryItem $roomInventoryItem): string
    {
        if (! empty($roomInventoryItem->qr_code)) {
            return (string) $roomInventoryItem->qr_code;
        }

        return route('room-inventory.item.show', $roomInventoryItem->id, false);
    }
}