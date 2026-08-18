<?php

namespace Tests\Feature;

use App\Models\KioskCategory;
use App\Models\KioskMinibarProductMap;
use App\Models\KioskProduct;
use App\Models\KioskUnit;
use App\Models\KioskUnitTransfer;
use App\Models\MinibarProduct;
use App\Models\MinibarProductCategory;
use App\Models\MinibarWarehouseStock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskToMinibarTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private KioskProduct $kioskProduct;
    private KioskProduct $otherKioskProduct;
    private MinibarProduct $minibarProduct;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Kiosk to minibar transfer feature tests require MySQL-compatible schema.');
        }

        Carbon::setTestNow(Carbon::parse('2026-08-03'));

        $this->user = User::create([
            'name' => 'Inventario Test',
            'email' => 'inventario.transfer@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
        $this->withoutMiddleware(\App\Http\Middleware\ValidatePermission::class);

        $kioskCategory = KioskCategory::create([
            'name' => 'Bebidas Kiosko',
            'active' => true,
        ]);

        $this->kioskProduct = KioskProduct::create([
            'name' => 'Agua 500ml',
            'code' => 'AGUA500',
            'category_id' => $kioskCategory->id,
            'active' => true,
            'sale_price' => 3000,
        ]);

        $this->otherKioskProduct = KioskProduct::create([
            'name' => 'Gaseosa 350ml',
            'code' => 'GAS350',
            'category_id' => $kioskCategory->id,
            'active' => true,
            'sale_price' => 4000,
        ]);

        $minibarCategory = MinibarProductCategory::create([
            'name' => 'Bebidas Minibar',
            'active' => true,
        ]);

        $this->minibarProduct = MinibarProduct::create([
            'name' => 'Agua 500ml',
            'category_id' => $minibarCategory->id,
            'is_sellable' => true,
            'sale_price' => 5000,
            'purchase_price' => 2000,
            'unit' => 'unidad',
            'barcode' => 'AGUA500',
            'active' => true,
        ]);
    }

    private function createUnit(
        ?KioskProduct $product = null,
        array $overrides = []
    ): KioskUnit {
        return KioskUnit::create(array_merge([
            'product_id' => ($product ?? $this->kioskProduct)->id,
            'code_complement' => 'U-' . uniqid(),
            'price' => 3000,
            'active' => true,
            'sold' => false,
        ], $overrides));
    }

    public function test_transfers_units_to_minibar_warehouse_and_marks_them(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();

        $response = $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unitA->id, $unitB->id],
            'minibar_product_id' => $this->minibarProduct->id,
            'notes' => 'Traslado quincenal',
        ]);

        $response->assertOk()
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('minibar_product_id', $this->minibarProduct->id);

        $this->assertNotNull($unitA->fresh()->transferred_at);
        $this->assertNotNull($unitB->fresh()->transferred_at);

        $stock = MinibarWarehouseStock::where('product_id', $this->minibarProduct->id)->first();
        $this->assertEquals(2, $stock->current_quantity);

        $this->assertSame(
            2,
            KioskUnitTransfer::where('minibar_product_id', $this->minibarProduct->id)->count()
        );
        $this->assertDatabaseHas('kiosk_unit_transfers', [
            'unit_id' => $unitA->id,
            'transferred_by' => $this->user->id,
        ]);
    }

    public function test_transfer_by_quantity_selects_earliest_expiring_units_first(): void
    {
        $this->createUnit($this->kioskProduct, ['expiration' => '2026-10-01']);
        $this->createUnit($this->kioskProduct, ['expiration' => '2026-09-01']);
        $this->createUnit($this->kioskProduct, ['expiration' => '2026-11-01']);

        $response = $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'kiosk_product_id' => $this->kioskProduct->id,
            'quantity' => 2,
            'minibar_product_id' => $this->minibarProduct->id,
        ]);

        $response->assertOk()->assertJsonPath('quantity', 2);

        $transferred = KioskUnitTransfer::with('unit')->get()->pluck('unit.expiration')->map(
            fn ($exp) => $exp->toDateString()
        )->sort()->values()->all();

        $this->assertEquals(['2026-09-01', '2026-10-01'], $transferred);
    }

    public function test_cannot_transfer_sold_unit(): void
    {
        $unit = $this->createUnit($this->kioskProduct, ['sold' => true]);

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unit->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertUnprocessable();
    }

    public function test_cannot_transfer_expired_unit(): void
    {
        $unit = $this->createUnit($this->kioskProduct, ['expiration' => '2026-07-01']);

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unit->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertUnprocessable();
    }

    public function test_cannot_transfer_inactive_unit(): void
    {
        $unit = $this->createUnit($this->kioskProduct, ['active' => false]);

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unit->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertUnprocessable();
    }

    public function test_cannot_transfer_already_transferred_unit(): void
    {
        $unit = $this->createUnit($this->kioskProduct, ['transferred_at' => '2026-08-01 10:00:00']);

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unit->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertUnprocessable();
    }

    public function test_rejects_units_from_different_kiosk_products(): void
    {
        $unitA = $this->createUnit($this->kioskProduct);
        $unitB = $this->createUnit($this->otherKioskProduct);

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unitA->id, $unitB->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertUnprocessable();
    }

    public function test_rejects_quantity_greater_than_available(): void
    {
        $this->createUnit($this->kioskProduct);

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'kiosk_product_id' => $this->kioskProduct->id,
            'quantity' => 5,
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertUnprocessable();
    }

    public function test_transfer_persists_mapping_and_reuses_it(): void
    {
        $unit = $this->createUnit();

        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$unit->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertOk();

        $this->assertDatabaseHas('kiosk_minibar_product_map', [
            'kiosk_product_id' => $this->kioskProduct->id,
            'minibar_product_id' => $this->minibarProduct->id,
            'match_source' => 'manual',
        ]);

        $secondUnit = $this->createUnit();
        $this->postJson('/api/kiosk/units/transfer-to-minibar', [
            'unit_ids' => [$secondUnit->id],
            'minibar_product_id' => $this->minibarProduct->id,
        ])->assertOk();

        $this->assertSame(
            1,
            KioskMinibarProductMap::where('kiosk_product_id', $this->kioskProduct->id)->count()
        );
    }

    public function test_mapping_endpoint_creates_persisted_mapping(): void
    {
        $this->postJson('/api/kiosk/minibar/map', [
            'kiosk_product_id' => $this->kioskProduct->id,
            'minibar_product_id' => $this->minibarProduct->id,
            'match_source' => 'auto',
        ])->assertOk();

        $this->assertDatabaseHas('kiosk_minibar_product_map', [
            'kiosk_product_id' => $this->kioskProduct->id,
            'minibar_product_id' => $this->minibarProduct->id,
            'match_source' => 'auto',
        ]);
    }

    public function test_suggestions_match_by_name_or_barcode(): void
    {
        $response = $this->getJson('/api/kiosk/minibar/suggestions?kiosk_product_id=' . $this->kioskProduct->id);

        $response->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($this->minibarProduct->id));
    }
}
