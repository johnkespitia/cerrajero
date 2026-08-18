<?php

namespace Tests\Feature;

use App\Models\RoomInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoomInventoryItemQrEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operador Inventario',
            'email' => 'inventory@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
    }

    public function test_index_items_does_not_crash_when_qr_code_column_missing(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Column drop test requires MySQL.');
        }

        if (Schema::hasColumn('room_inventory_items', 'qr_code')) {
            Schema::table('room_inventory_items', function ($table) {
                $table->dropColumn('qr_code');
            });
        }

        $response = $this->getJson('/api/room-inventory/items');

        $response->assertOk();

        Schema::table('room_inventory_items', function ($table) {
            $table->string('qr_code', 500)->nullable();
        });
    }

    public function test_store_item_auto_generates_qr_code_when_missing(): void
    {
        $response = $this->postJson('/api/room-inventory/items', [
            'name' => 'Televisor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('item.name', 'Televisor');

        $this->assertNotEmpty($response->json('item.qr_code'));
    }

    public function test_store_item_rejects_duplicate_qr_code(): void
    {
        $value = 'DUPLICATED-QR';

        RoomInventoryItem::create([
            'name' => 'Item A',
            'qr_code' => $value,
        ]);

        $response = $this->postJson('/api/room-inventory/items', [
            'name' => 'Item B',
            'qr_code' => $value,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['qr_code']);
    }

    public function test_generate_returns_json_with_svg_payload(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Routes test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Aire acondicionado',
            'qr_code' => 'STABLE-PAYLOAD',
        ]);

        $response = $this->getJson("/api/room-inventory/qr/items/{$item->id}");

        $response->assertOk()
            ->assertJsonStructure(['id', 'name', 'qr_code', 'url']);
    }

    public function test_download_svg_returns_svg_content_type(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Routes test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Cafetera',
        ]);

        $response = $this->get("/api/room-inventory/qr/items/{$item->id}/svg");

        $response->assertOk();
        $this->assertStringStartsWith('image/svg+xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<svg', ltrim($response->getContent()));
    }

    public function test_download_png_returns_png_content_type(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Routes test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Espejo',
        ]);

        $response = $this->get("/api/room-inventory/qr/items/{$item->id}/png");

        $response->assertOk();
        $this->assertStringStartsWith('image/png', (string) $response->headers->get('Content-Type'));
        $this->assertSame("\x89PNG", substr($response->getContent(), 0, 4));
    }

    public function test_generate_requires_room_inventory_item_list_permission(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Routes test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Cortinas',
        ]);

        $unauthorized = User::create([
            'name' => 'Sin permiso',
            'email' => 'noperm@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($unauthorized)
            ->getJson("/api/room-inventory/qr/items/{$item->id}");

        $response->assertForbidden();
    }

    public function test_regenerate_endpoint_writes_audit_log(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Routes test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Refrigerador',
            'qr_code' => 'INITIAL-QR',
        ]);

        $response = $this->postJson("/api/room-inventory/qr/items/{$item->id}/regenerate");

        $response->assertOk()
            ->assertJsonStructure(['message', 'item' => ['id', 'qr_code']]);

        $this->assertDatabaseHas('room_inventory_history', [
            'item_id' => $item->id,
            'action' => 'qr_regenerated',
        ]);
    }
}