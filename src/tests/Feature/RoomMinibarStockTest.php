<?php

namespace Tests\Feature;

use App\Models\MinibarProduct;
use App\Models\MinibarProductCategory;
use App\Models\MinibarWarehouseStock;
use App\Models\Room;
use App\Models\RoomMinibarStock;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomMinibarStockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Room $room;
    private MinibarProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Room minibar stock feature tests require MySQL-compatible schema.');
        }

        $this->user = User::create([
            'name' => 'Minibar Test',
            'email' => 'minibar.stock@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
        $this->withoutMiddleware(\App\Http\Middleware\ValidatePermission::class);

        $roomType = RoomType::create([
            'name' => 'Estándar',
            'code' => 'STD',
            'default_capacity' => 2,
            'max_capacity' => 2,
            'active' => true,
        ]);

        $this->room = Room::create([
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'name' => '101',
            'status' => 'available',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 200000,
        ]);

        $category = MinibarProductCategory::create([
            'name' => 'Cervezas',
            'active' => true,
        ]);

        $this->product = MinibarProduct::create([
            'name' => 'Cerveza 330ml',
            'category_id' => $category->id,
            'is_sellable' => true,
            'sale_price' => 8000,
            'purchase_price' => 4000,
            'unit' => 'unidad',
            'active' => true,
        ]);
    }

    private function setWarehouse(int $quantity): void
    {
        MinibarWarehouseStock::updateOrCreate(
            ['product_id' => $this->product->id],
            ['current_quantity' => $quantity]
        );
    }

    private function setRoomStock(Room $room, int $quantity): void
    {
        RoomMinibarStock::create([
            'room_id' => $room->id,
            'product_id' => $this->product->id,
            'standard_quantity' => $quantity,
            'current_quantity' => $quantity,
            'active' => true,
        ]);
    }

    private function warehouseQuantity(): int
    {
        $stock = MinibarWarehouseStock::where('product_id', $this->product->id)->first();
        return $stock ? (int) $stock->current_quantity : 0;
    }

    public function test_store_with_zero_quantity_succeeds_when_rooms_exceed_warehouse(): void
    {
        $this->setWarehouse(6);

        // 4 habitaciones ya tienen 2 cada una (8 en total), sin descontar de bodega
        foreach ([1, 2, 3, 4] as $i) {
            $this->setRoomStock(Room::create([
                'room_type_id' => $this->room->room_type_id,
                'room_number' => "10{$i}",
                'name' => "10{$i}",
                'status' => 'available',
                'active' => true,
                'capacity' => 2,
                'max_capacity' => 2,
                'room_price' => 200000,
            ]), 2);
        }

        // Crear el stock de la 5ta habitación con Cantidad Actual 0 debe funcionar
        $this->postJson("/api/rooms/{$this->room->id}/minibar/stock", [
            'product_id' => $this->product->id,
            'standard_quantity' => 2,
            'current_quantity' => 0,
        ])->assertOk();

        $this->assertEquals(6, $this->warehouseQuantity());

        // Reponer 2 unidades sí es válido (2 <= 6 en bodega)
        $this->postJson("/api/rooms/{$this->room->id}/minibar/restock", [
            'products' => [$this->product->id => 2],
            'reason' => 'manual',
        ])->assertOk();

        $this->assertEquals(4, $this->warehouseQuantity());
        $this->assertEquals(
            2,
            RoomMinibarStock::where('room_id', $this->room->id)
                ->where('product_id', $this->product->id)
                ->first()->current_quantity
        );
    }

    public function test_store_rejects_when_delta_exceeds_warehouse(): void
    {
        $this->setWarehouse(2);

        $this->postJson("/api/rooms/{$this->room->id}/minibar/stock", [
            'product_id' => $this->product->id,
            'standard_quantity' => 3,
            'current_quantity' => 3,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'No hay suficiente inventario en bodega para Cerveza 330ml. Disponible en bodega: 2, solicitado: 3.');
    }

    public function test_store_deducts_delta_from_warehouse(): void
    {
        $this->setWarehouse(6);

        $this->postJson("/api/rooms/{$this->room->id}/minibar/stock", [
            'product_id' => $this->product->id,
            'standard_quantity' => 2,
            'current_quantity' => 2,
        ])->assertOk();

        $this->assertEquals(4, $this->warehouseQuantity());
    }

    public function test_update_with_zero_returns_stock_to_warehouse(): void
    {
        $this->setWarehouse(6);

        $this->postJson("/api/rooms/{$this->room->id}/minibar/stock", [
            'product_id' => $this->product->id,
            'standard_quantity' => 2,
            'current_quantity' => 2,
        ])->assertOk();
        $this->assertEquals(4, $this->warehouseQuantity());

        $stock = RoomMinibarStock::where('room_id', $this->room->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->putJson("/api/rooms/{$this->room->id}/minibar/stock/{$stock->id}", [
            'current_quantity' => 0,
        ])->assertOk();

        $this->assertEquals(6, $this->warehouseQuantity());
    }
}
