<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21DebitNoteBuilder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class Ubl21DebitNoteBuilderTest extends TestCase
{
    /** @var Ubl21DebitNoteBuilder */
    private $builder;

    /** @var string */
    private $cude;

    /** @var string */
    private $parentCufe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new Ubl21DebitNoteBuilder();
        $this->cude = str_repeat('e', 96);
        $this->parentCufe = str_repeat('f', 96);
    }

    public function test_metadata_reports_nd(): void
    {
        $this->assertSame(DocumentType::ND, $this->builder->documentType());
    }

    public function test_root_is_debit_note(): void
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($this->builder->build($this->validPayload())));
        $this->assertSame('DebitNote', $doc->documentElement->localName);
        $this->assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2',
            $doc->documentElement->namespaceURI
        );
    }

    public function test_debit_note_type_code_present(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $nodes = $xp->query('//cbc:DebitNoteTypeCode');
        $this->assertSame(1, $nodes->length);
        $this->assertSame('92', $nodes->item(0)->textContent);
    }

    public function test_billing_reference_carries_parent_cufe(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $uuid = $xp->query('//cac:BillingReference/cac:InvoiceDocumentReference/cbc:UUID')->item(0);
        $this->assertNotNull($uuid);
        $this->assertSame($this->parentCufe, $uuid->textContent);
    }

    public function test_lines_use_debit_note_line_and_debited_quantity(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $this->assertSame(1, $xp->query('//cac:DebitNoteLine')->length);
        $qty = $xp->query('//cac:DebitNoteLine/cbc:DebitedQuantity')->item(0);
        $this->assertNotNull($qty);
        $this->assertSame('NIU', $qty->getAttribute('unitCode'));
    }

    public function test_missing_reference_throws(): void
    {
        $payload = $this->validPayload();
        unset($payload['references']);

        $this->expectException(IncompleteUblPayloadException::class);
        $this->builder->build($payload);
    }

    private function validPayload(): array
    {
        return [
            'document' => [
                'type' => DocumentType::ND,
                'prefix' => 'ND',
                'sequence' => 1,
                'number' => 'ND1',
                'issue_date' => '2026-03-26',
                'issue_time' => '11:30:00-05:00',
                'currency' => 'COP',
                'environment' => '2',
                'cufe' => $this->cude,
            ],
            'supplier' => [
                'nit' => '900000000',
                'name' => 'CAMPO VERDE S.A.S.',
                'id_type' => '31',
            ],
            'customer' => [
                'id' => '1010101010',
                'id_type' => '13',
                'name' => 'Cliente ND',
            ],
            'lines' => [
                [
                    'sequence' => '1',
                    'description' => 'Recargo financiero',
                    'quantity' => '1',
                    'unit_price' => '5000.00',
                    'line_total' => '5000.00',
                ],
            ],
            'totals' => [
                'line_extension_amount' => '5000.00',
                'tax_exclusive_amount' => '5000.00',
                'tax_inclusive_amount' => '5950.00',
                'payable_amount' => '5950.00',
            ],
            'references' => [
                [
                    'cufe' => $this->parentCufe,
                    'number' => 'SETP990000001',
                    'issue_date' => '2026-03-25',
                ],
            ],
        ];
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        return $xp;
    }
}
