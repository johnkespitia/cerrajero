<?php

namespace App\Http\Controllers\Api\RoomInventory;

use App\Http\Controllers\Controller;
use App\Models\RoomInventoryItem;
use BaconQrCode\Renderer\RendererStyle\BasicStyle;
use BaconQrCode\Renderer\Image\Svg\SvgImageRenderer;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrCodeController extends Controller
{
    public function generate(RoomInventoryItem $item): JsonResponse
    {
        $url = route('room-inventory.item.show', $item->id);

        $writer = new Writer(new SvgImageRenderer(new BasicStyle(300, 300)));
        $svg = $writer->write($url, 3);

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'qr_code' => $svg,
            'url' => $url,
        ]);
    }

    public function downloadSvg(RoomInventoryItem $item): \Symfony\Component\HttpFoundation\Response
    {
        $url = route('room-inventory.item.show', $item->id);

        $writer = new Writer(new SvgImageRenderer(new BasicStyle(300, 300)));
        $svg = $writer->write($url, 3);

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $item->id . '.svg"');
    }

    public function downloadPng(RoomInventoryItem $item): \Symfony\Component\HttpFoundation\Response
    {
        $url = route('room-inventory.item.show', $item->id);

        $writer = new Writer(new \BaconQrCode\Renderer\Image\Png\PngImageRenderer());
        $png = $writer->write($url, 3);

        return response($png, 200)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $item->id . '.png"');
    }
}