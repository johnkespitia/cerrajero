<?php

namespace Tests\Feature;

use App\Models\RoomInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomInventoryPrintSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Print-sheet test requires MySQL.');
        }

        $this->user = User::create([
            'name' => 'Operador Print',
            'email' => 'print@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
    }

    public function test_print_sheet_returns_html_with_svg_labels(): void
    {
        $items = collect();
        for ($i = 0; $i < 3; $i++) {
            $items->push(RoomInventoryItem::create([
                'name' => "Articulo {$i}",
                'qr_code' => "QR-{$i}",
            ]));
        }

        $ids = $items->pluck('id')->implode(',');

        $response = $this->get("/api/room-inventory/qr/print-sheet?ids={$ids}");

        $response->assertOk();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringContainsString('<svg', $body);
        $svgCount = substr_count($body, '<svg');
        $this->assertSame(3, $svgCount);
        $this->assertStringContainsString('Articulo 0', $body);
        $this->assertStringContainsString('Articulo 2', $body);
    }

    public function test_print_sheet_rejects_empty_ids(): void
    {
        $response = $this->get('/api/room-inventory/qr/print-sheet?ids=');

        $response->assertStatus(422);
    }

    public function test_print_sheet_rejects_too_many_ids(): void
    {
        $ids = implode(',', range(1, 241));

        $response = $this->get("/api/room-inventory/qr/print-sheet?ids={$ids}");

        $response->assertStatus(422);
    }

    public function test_print_sheet_returns_404_when_no_items_match(): void
    {
        $response = $this->get('/api/room-inventory/qr/print-sheet?ids=999999,888888');

        $response->assertNotFound();
    }

    public function test_print_sheet_requires_permission(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Permission test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Impresora',
            'qr_code' => 'PRINT-QR',
        ]);

        $unauthorized = User::create([
            'name' => 'Sin permisos',
            'email' => 'noperm-print@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($unauthorized)
            ->get("/api/room-inventory/qr/print-sheet?ids={$item->id}");

        $response->assertForbidden();
    }
}