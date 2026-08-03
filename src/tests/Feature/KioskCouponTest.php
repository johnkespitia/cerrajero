<?php

namespace Tests\Feature;

use App\Models\CashRegisterClosure;
use App\Models\Customer;
use App\Models\KioskCategory;
use App\Models\KioskCoupon;
use App\Models\KioskProduct;
use App\Models\KioskUnit;
use App\Models\PaymentType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskCouponTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private PaymentType $paymentType;
    private KioskUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Kiosk coupon feature tests require MySQL-compatible schema.');
        }

        Carbon::setTestNow(Carbon::parse('2026-08-03'));

        $this->user = User::create([
            'name' => 'Cajero Test',
            'email' => 'cajero.coupon@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
        $this->withoutMiddleware(\App\Http\Middleware\ValidatePermission::class);

        $this->customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '1234567890',
            'name' => 'Cliente',
            'last_name' => 'Kiosko',
            'email' => 'cliente.kiosko@example.com',
            'active' => true,
        ]);

        $this->paymentType = PaymentType::create([
            'name' => 'Efectivo',
            'active' => true,
            'credit' => false,
            'calculator' => true,
        ]);

        $category = KioskCategory::create([
            'name' => 'Bebidas',
            'active' => true,
        ]);

        $product = KioskProduct::create([
            'name' => 'Agua',
            'code' => 'AGUA01',
            'category_id' => $category->id,
            'active' => true,
            'sale_price' => 100000,
        ]);

        $this->unit = KioskUnit::create([
            'product_id' => $product->id,
            'code_complement' => '001',
            'price' => 100000,
            'active' => true,
            'sold' => false,
        ]);
    }

    private function invoicePayload(array $extra = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'payment_code' => 'PAY-' . uniqid(),
            'payment_type_id' => $this->paymentType->id,
            'electronic_invoice' => false,
            'units' => [
                ['kiosk_units_id' => $this->unit->id, 'price' => 100000],
            ],
        ], $extra);
    }

    private function makeCoupon(array $overrides = []): KioskCoupon
    {
        return KioskCoupon::create(array_merge([
            'code' => 'VERANO10',
            'name' => 'Verano 10%',
            'type' => 'percentage',
            'value' => 10,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-08-31',
            'max_uses' => null,
            'used_count' => 0,
            'active' => true,
        ], $overrides));
    }

    private function createAvailableUnit(float $price = 100000): KioskUnit
    {
        $product = KioskProduct::first();
        $suffix = uniqid();

        return KioskUnit::create([
            'product_id' => $product->id,
            'code_complement' => 'U-' . $suffix,
            'price' => $price,
            'active' => true,
            'sold' => false,
        ]);
    }

    public function test_can_create_list_and_update_coupon(): void
    {
        $create = $this->postJson('/api/kiosk/coupons', [
            'code' => 'promo20',
            'name' => 'Promo 20%',
            'type' => 'percentage',
            'value' => 20,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
            'max_uses' => null,
            'active' => true,
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('code', 'PROMO20')
            ->assertJsonPath('type', 'percentage');

        $id = $create->json('id');

        $this->getJson('/api/kiosk/coupons')
            ->assertStatus(200)
            ->assertJsonCount(1);

        $this->putJson("/api/kiosk/coupons/{$id}", [
            'code' => 'PROMO20',
            'name' => 'Promo 20% actualizado',
            'type' => 'fixed',
            'value' => 5000,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
            'max_uses' => 10,
            'active' => true,
        ])->assertStatus(200)
            ->assertJsonPath('name', 'Promo 20% actualizado')
            ->assertJsonPath('type', 'fixed')
            ->assertJsonPath('max_uses', 10);
    }

    public function test_can_delete_unused_coupon(): void
    {
        $coupon = KioskCoupon::create([
            'code' => 'DELETEME',
            'name' => 'Temp',
            'type' => 'fixed',
            'value' => 1000,
            'valid_from' => '2026-08-01',
            'valid_until' => '2026-12-31',
            'active' => true,
        ]);

        $this->deleteJson("/api/kiosk/coupons/{$coupon->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('kiosk_coupons', ['id' => $coupon->id]);
    }

    public function test_invoice_with_coupon_applies_discount_and_increments_usage(): void
    {
        $this->makeCoupon();

        $response = $this->postJson('/api/kiosk/invoice', $this->invoicePayload([
            'coupon_code' => 'VERANO10',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('coupon_code', 'VERANO10')
            ->assertJsonPath('coupon_discount', '10000.00')
            ->assertJsonPath('discount_total', '10000.00');

        $this->assertSame(1, (int) KioskCoupon::where('code', 'VERANO10')->value('used_count'));
    }

    public function test_invoice_with_manual_discount_records_applied_by(): void
    {
        $response = $this->postJson('/api/kiosk/invoice', $this->invoicePayload([
            'manual_discount' => 5000,
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('manual_discount', '5000.00')
            ->assertJsonPath('manual_discount_by', $this->user->id);
    }

    public function test_invoice_with_coupon_and_manual_discount(): void
    {
        $this->makeCoupon();

        $response = $this->postJson('/api/kiosk/invoice', $this->invoicePayload([
            'coupon_code' => 'VERANO10',
            'manual_discount' => 5000,
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('coupon_discount', '10000.00')
            ->assertJsonPath('manual_discount', '5000.00')
            ->assertJsonPath('discount_total', '15000.00');
    }

    public function test_invalid_coupon_on_invoice_returns_422(): void
    {
        $response = $this->postJson('/api/kiosk/invoice', $this->invoicePayload([
            'coupon_code' => 'INVALID',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);
    }

    public function test_exhausted_coupon_on_invoice_returns_422(): void
    {
        $this->makeCoupon(['max_uses' => 1, 'used_count' => 1]);

        $response = $this->postJson('/api/kiosk/invoice', $this->invoicePayload([
            'coupon_code' => 'VERANO10',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);
    }

    public function test_pending_invoice_pay_applies_discount_and_increments_coupon_once(): void
    {
        $this->makeCoupon();
        $unit = $this->createAvailableUnit();

        $pending = $this->postJson('/api/kiosk/invoice', [
            'customer_id' => $this->customer->id,
            'electronic_invoice' => false,
            'pending' => true,
            'units' => [
                ['kiosk_units_id' => $unit->id, 'price' => 100000],
            ],
        ]);

        $pending->assertStatus(201);
        $invoiceId = $pending->json('id');

        $this->assertSame(0, (int) KioskCoupon::where('code', 'VERANO10')->value('used_count'));

        $pay = $this->postJson("/api/kiosk/invoice/{$invoiceId}/pay", [
            'payment_type_id' => $this->paymentType->id,
            'payment_code' => 'PAY-PENDING-1',
            'coupon_code' => 'VERANO10',
        ]);

        $pay->assertStatus(200)
            ->assertJsonPath('coupon_discount', '10000.00')
            ->assertJsonPath('payed', true);

        $this->assertSame(1, (int) KioskCoupon::where('code', 'VERANO10')->value('used_count'));
    }

    public function test_cash_register_closure_uses_net_total_with_discount(): void
    {
        $this->makeCoupon([
            'code' => 'FIJO20',
            'type' => 'fixed',
            'value' => 20000,
        ]);

        $this->postJson('/api/kiosk/invoice', $this->invoicePayload([
            'coupon_code' => 'FIJO20',
        ]))->assertStatus(201);

        $closure = CashRegisterClosure::first();
        $this->assertNotNull($closure);
        $closure->calculateTotals();

        $this->assertEquals(80000.0, (float) $closure->total_sales);
    }
}
