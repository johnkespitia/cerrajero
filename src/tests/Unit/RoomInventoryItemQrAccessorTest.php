<?php

namespace Tests\Unit;

use App\Models\RoomInventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomInventoryItemQrAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_code_is_auto_generated_when_missing(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'TV 55 pulgadas',
        ]);

        $this->assertNotEmpty($item->qr_code);
        $this->assertIsString($item->qr_code);
    }

    public function test_explicit_qr_code_is_persisted_unchanged(): void
    {
        $value = 'CUSTOM-QR-123';

        $item = RoomInventoryItem::create([
            'name' => 'Aire acondicionado',
            'qr_code' => $value,
        ]);

        $this->assertSame($value, $item->qr_code);
    }

    public function test_to_array_does_not_throw_when_qr_code_is_null(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'Cama king',
            'qr_code' => null,
        ]);

        $array = $item->fresh()->toArray();

        $this->assertArrayHasKey('qr_code', $array);
        $this->assertNotEmpty($array['qr_code']);
    }

    public function test_to_json_does_not_throw_undefined_property(): void
    {
        $item = RoomInventoryItem::create([
            'name' => 'Mesa de noche',
        ]);

        $json = $item->fresh()->toJson();

        $this->assertIsString($json);
        $this->assertStringContainsString('"qr_code"', $json);
    }

    public function test_accessor_returns_persisted_value_without_recursion(): void
    {
        $value = 'STABLE-IDENTIFIER';

        $item = RoomInventoryItem::create([
            'name' => 'Silla',
            'qr_code' => $value,
        ]);

        $fresh = RoomInventoryItem::find($item->id);

        $this->assertSame($value, $fresh->qr_code);
    }
}