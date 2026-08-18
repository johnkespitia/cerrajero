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

    protected function resolvePayload(RoomInventoryItem $roomInventoryItem): string
    {
        if (! empty($roomInventoryItem->qr_code)) {
            return (string) $roomInventoryItem->qr_code;
        }

        return route('room-inventory.item.show', $roomInventoryItem->id, false);
    }
}