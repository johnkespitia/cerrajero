<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Models\ElectronicDocument;
use App\Models\KioskInvoice;
use App\Models\Reservation;
use App\Services\ElectronicInvoicing\DocumentResolver;
use InvalidArgumentException;
use Tests\TestCase;

class DocumentResolverTest extends TestCase
{
    /** @var DocumentResolver */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DocumentResolver();
    }

    public function test_reservation_checkout_always_resolves_to_fev(): void
    {
        $reservation = new Reservation([
            'reservation_number' => 'R-001',
            'customer_id' => 1,
        ]);

        $this->assertSame(DocumentType::FEV, $this->resolver->forReservation($reservation));
    }

    public function test_kiosk_invoice_with_electronic_invoice_and_acquirer_resolves_to_fev(): void
    {
        $invoice = new KioskInvoice([
            'electronic_invoice' => true,
            'customer_id' => 1,
        ]);
        $invoice->acquirer_id = 42;

        $this->assertSame(DocumentType::FEV, $this->resolver->forKioskInvoice($invoice));
    }

    public function test_kiosk_invoice_without_electronic_invoice_resolves_to_dee_pos(): void
    {
        $invoice = new KioskInvoice([
            'electronic_invoice' => false,
        ]);
        $invoice->acquirer_id = 42;

        $this->assertSame(DocumentType::DEE_POS, $this->resolver->forKioskInvoice($invoice));
    }

    public function test_kiosk_invoice_without_acquirer_resolves_to_dee_pos(): void
    {
        $invoice = new KioskInvoice([
            'electronic_invoice' => true,
        ]);
        $invoice->acquirer_id = null;

        $this->assertSame(DocumentType::DEE_POS, $this->resolver->forKioskInvoice($invoice));
    }

    public function test_cancellation_of_fev_resolves_to_nc(): void
    {
        $original = new ElectronicDocument(['document_type' => DocumentType::FEV]);

        $this->assertSame(DocumentType::NC, $this->resolver->forCancellation($original));
    }

    public function test_debit_adjustment_of_dee_pos_resolves_to_nd(): void
    {
        $original = new ElectronicDocument(['document_type' => DocumentType::DEE_POS]);

        $this->assertSame(DocumentType::ND, $this->resolver->forDebitAdjustment($original));
    }

    public function test_cancellation_of_nc_is_rejected(): void
    {
        $original = new ElectronicDocument(['document_type' => DocumentType::NC]);

        $this->expectException(InvalidArgumentException::class);
        $this->resolver->forCancellation($original);
    }

    public function test_describe_flags_acquirer_requirement_per_document_type(): void
    {
        $fev = $this->resolver->describe(DocumentType::FEV);
        $this->assertTrue($fev['requires_acquirer']);
        $this->assertFalse($fev['references_original']);

        $deePos = $this->resolver->describe(DocumentType::DEE_POS);
        $this->assertFalse($deePos['requires_acquirer']);
        $this->assertFalse($deePos['references_original']);

        $nc = $this->resolver->describe(DocumentType::NC);
        $this->assertTrue($nc['requires_acquirer']);
        $this->assertTrue($nc['references_original']);
    }
}
