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

    public function generate(RoomInventoryItem $item): JsonResponse
    {
        $payload = $this->resolvePayload($item);

        $writer = new Writer(new SvgImageRenderer(new BasicStyle(300, 300)));
        $svg = $writer->write($payload, 3);

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'qr_code' => $svg,
            'url' => $payload,
        ]);
    }

    public function downloadSvg(RoomInventoryItem $item): Response
    {
        $payload = $this->resolvePayload($item);

        $writer = new Writer(new SvgImageRenderer(new BasicStyle(300, 300)));
        $svg = $writer->write($payload, 3);

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $item->id . '.svg"');
    }

    public function downloadPng(RoomInventoryItem $item): Response
    {
        $payload = $this->resolvePayload($item);

        $writer = new Writer(new PngImageRenderer());
        $png = $writer->write($payload, 3);

        return response($png, 200)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $item->id . '.png"');
    }

    public function regenerate(Request $request, RoomInventoryItem $item): JsonResponse
    {
        $previous = $item->qr_code;
        $item->forceFill(['qr_code' => (string) Str::uuid()])->save();
        $item->refresh();

        $this->auditService->log('qr_regenerated', [
            'item_id' => $item->id,
            'assignable_type' => null,
            'assignable_id' => null,
            'notes' => 'QR regenerado' . ($previous ? " (anterior: {$previous})" : ''),
        ], null, $request);

        return response()->json([
            'message' => 'QR regenerado exitosamente',
            'item' => $item,
        ]);
    }

    protected function resolvePayload(RoomInventoryItem $item): string
    {
        if (! empty($item->qr_code)) {
            return (string) $item->qr_code;
        }

        return route('room-inventory.item.show', $item->id, false);
    }
}