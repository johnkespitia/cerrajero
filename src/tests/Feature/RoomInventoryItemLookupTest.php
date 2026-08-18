<?php

namespace Tests\Feature;

use App\Models\RoomInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomInventoryItemLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Lookup test requires MySQL.');
        }

        $this->user = User::create([
            'name' => 'Operador Lookup',
            'email' => 'lookup@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
    }

    public function test_lookup_by_exact_qr_code(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'Televisor',
            'qr_code' => 'CUSTOM-QR-001',
        ]);

        $response = $this->getJson('/api/room-inventory/items/lookup?qr=CUSTOM-QR-001');

        $response->assertOk()
            ->assertJsonPath('id', $item->id)
            ->assertJsonPath('name', 'Televisor')
            ->assertJsonPath('qr_code', 'CUSTOM-QR-001');
    }

    public function test_lookup_by_uuid_persisted(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'Aire acondicionado',
        ]);

        $this->assertNotEmpty($item->qr_code);

        $response = $this->getJson('/api/room-inventory/items/lookup?qr=' . $item->qr_code);

        $response->assertOk()
            ->assertJsonPath('id', $item->id);
    }

    public function test_lookup_by_legacy_url_in_qr(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'Cafetera',
        ]);

        // Simular QR legacy: el QR codifica la URL pública del item.
        $legacyUrl = url('/api/room-inventory/items/' . $item->id);
        $item->update(['qr_code' => $legacyUrl]);

        $response = $this->getJson('/api/room-inventory/items/lookup?qr=' . urlencode($legacyUrl));

        $response->assertOk()
            ->assertJsonPath('id', $item->id);
    }

    public function test_lookup_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/room-inventory/items/lookup?qr=DOES-NOT-EXIST');

        $response->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_lookup_requires_qr_parameter(): void
    {
        $response = $this->getJson('/api/room-inventory/items/lookup');

        $response->assertStatus(422);
    }

    public function test_lookup_registers_qr_scanned_history(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'Espejo',
            'qr_code' => 'TRACKED-QR',
        ]);

        $this->getJson('/api/room-inventory/items/lookup?qr=TRACKED-QR');

        $this->assertDatabaseHas('room_inventory_history', [
            'item_id' => $item->id,
            'action' => 'qr_scanned',
        ]);
    }

    public function test_lookup_requires_permission(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Permission test requires MySQL.');
        }

        $item = RoomInventoryItem::create([
            'name' => 'Cortinas',
            'qr_code' => 'PERM-QR',
        ]);

        $unauthorized = User::create([
            'name' => 'Sin permisos',
            'email' => 'noperm-lookup@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($unauthorized)
            ->getJson('/api/room-inventory/items/lookup?qr=PERM-QR');

        $response->assertForbidden();
    }
}