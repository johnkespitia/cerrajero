<?php

namespace Tests\Unit;

use App\Models\CashRegisterClosure;
use App\Models\Customer;
use App\Models\KioskInvoice;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\CashRegisterClosureService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los huecos frágiles del vínculo factura kiosko ↔ cierre de caja.
 * Requiere MySQL-compatible schema (phpunit local usa sqlite y se omite).
 */
class CashRegisterClosureServiceTest extends TestCase
{
    use RefreshDatabase;

    private CashRegisterClosureService $service;
    private User $user;
    private Customer $customer;
    private PaymentType $paymentType;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Cash register closure tests require MySQL-compatible schema.');
        }

        $this->service = app(CashRegisterClosureService::class);

        $this->user = User::create([
            'name' => 'Cajero',
            'email' => 'cajero@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '900100200',
            'name' => 'Cliente',
            'last_name' => 'Prueba',
            'email' => 'cliente.caja@example.com',
            'phone_number' => '3000000000',
            'active' => true,
        ]);

        $this->paymentType = PaymentType::create([
            'name' => 'Efectivo',
            'active' => true,
            'credit' => false,
        ]);
    }

    private function makeInvoice(?int $closureId = null): KioskInvoice
    {
        return KioskInvoice::create([
            'customer_id' => $this->customer->id,
            'payed' => true,
            'payment_code' => 'PAY-' . uniqid(),
            'payment_type_id' => $this->paymentType->id,
            'electronic_invoice' => false,
            'closure_id' => $closureId,
        ]);
    }

    public function test_assign_invoice_creates_open_closure_when_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 10:00:00', 'America/Bogota'));

        $invoice = $this->makeInvoice();
        $this->assertNull($invoice->closure_id);

        $closure = $this->service->assignInvoice($invoice, $this->user->id);

        $this->assertNotNull($closure);
        $this->assertFalse($closure->closed);
        $this->assertEquals($this->user->id, $closure->user_id);
        $this->assertEquals('2026-07-28', $closure->closure_date->toDateString());
        $this->assertEquals($closure->id, $invoice->fresh()->closure_id);
    }

    public function test_assign_invoice_reuses_existing_open_closure(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 11:00:00', 'America/Bogota'));

        $existing = CashRegisterClosure::create([
            'user_id' => $this->user->id,
            'closure_date' => '2026-07-28',
            'opening_balance' => 50000,
            'closed' => false,
        ]);

        $invoice = $this->makeInvoice();
        $closure = $this->service->assignInvoice($invoice, $this->user->id);

        $this->assertEquals($existing->id, $closure->id);
        $this->assertEquals(1, CashRegisterClosure::where('user_id', $this->user->id)->count());
    }

    public function test_assign_invoice_does_not_create_second_open_when_day_already_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 18:00:00', 'America/Bogota'));

        CashRegisterClosure::create([
            'user_id' => $this->user->id,
            'closure_date' => '2026-07-28',
            'opening_balance' => 0,
            'closed' => true,
            'closed_by' => $this->user->id,
            'closed_at' => now(),
        ]);

        $invoice = $this->makeInvoice();
        $closure = $this->service->assignInvoice($invoice, $this->user->id);

        $this->assertNull($closure);
        $this->assertNull($invoice->fresh()->closure_id);
    }

    public function test_attach_orphan_invoices_rescues_sales_before_opening_closure(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 09:00:00', 'America/Bogota'));

        $orphan = $this->makeInvoice();
        $this->assertNull($orphan->closure_id);

        $closure = CashRegisterClosure::create([
            'user_id' => $this->user->id,
            'closure_date' => '2026-07-28',
            'opening_balance' => 0,
            'closed' => false,
        ]);

        $attached = $this->service->attachOrphanInvoices($closure);

        $this->assertEquals(1, $attached);
        $this->assertEquals($closure->id, $orphan->fresh()->closure_id);
    }

    public function test_assign_does_not_reassign_invoice_already_linked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 12:00:00', 'America/Bogota'));

        $closureA = CashRegisterClosure::create([
            'user_id' => $this->user->id,
            'closure_date' => '2026-07-28',
            'opening_balance' => 0,
            'closed' => false,
        ]);

        $invoice = $this->makeInvoice($closureA->id);
        $returned = $this->service->assignInvoice($invoice, $this->user->id);

        $this->assertEquals($closureA->id, $returned->id);
        $this->assertEquals($closureA->id, $invoice->fresh()->closure_id);
    }
}
