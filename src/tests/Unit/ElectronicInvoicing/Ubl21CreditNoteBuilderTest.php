<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21CreditNoteBuilder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class Ubl21CreditNoteBuilderTest extends TestCase
{
    /** @var Ubl21CreditNoteBuilder */
    private $builder;

    /** @var string */
    private $cude;

    /** @var string */
    private $parentCufe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new Ubl21CreditNoteBuilder();
        $this->cude = str_repeat('c', 96);
        $this->parentCufe = str_repeat('d', 96);
    }

    public function test_metadata_reports_nc(): void
    {
        $this->assertSame(DocumentType::NC, $this->builder->documentType());
    }

    public function test_root_is_credit_note(): void
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($this->builder->build($this->validPayload())));
        $this->assertSame('CreditNote', $doc->documentElement->localName);
        $this->assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
            $doc->documentElement->namespaceURI
        );
    }

    public function test_credit_note_type_code_present(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $nodes = $xp->query('//cbc:CreditNoteTypeCode');
        $this->assertSame(1, $nodes->length);
        $this->assertSame('91', $nodes->item(0)->textContent);
    }

    public function test_billing_reference_carries_parent_cufe(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $uuid = $xp->query('//cac:BillingReference/cac:InvoiceDocumentReference/cbc:UUID')->item(0);
        $this->assertNotNull($uuid);
        $this->assertSame($this->parentCufe, $uuid->textContent);
        $this->assertSame('CUFE-SHA384', $uuid->getAttribute('schemeName'));
    }

    public function test_lines_use_credit_note_line_and_credited_quantity(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $this->assertSame(1, $xp->query('//cac:CreditNoteLine')->length);
        $qty = $xp->query('//cac:CreditNoteLine/cbc:CreditedQuantity')->item(0);
        $this->assertNotNull($qty);
        $this->assertSame('NIU', $qty->getAttribute('unitCode'));
    }

    public function test_missing_reference_throws(): void
    {
        $payload = $this->validPayload();
        unset($payload['references']);

        $this->expectException(IncompleteUblPayloadException::class);
        $this->expectExceptionMessage('references');
        $this->builder->build($payload);
    }

    public function test_discrepancy_response_is_emitted_when_supplied(): void
    {
        $payload = $this->validPayload();
        $payload['references'][0]['discrepancy_code'] = '2';
        $payload['references'][0]['discrepancy_description'] = 'Anulacion de factura';

        $xp = $this->xpath($this->builder->build($payload));
        $this->assertSame(
            '2',
            $xp->query('//cac:DiscrepancyResponse/cbc:ResponseCode')->item(0)->textContent
        );
    }

    private function validPayload(): array
    {
        return [
            'document' => [
                'type' => DocumentType::NC,
                'prefix' => 'NC',
                'sequence' => 1,
                'number' => 'NC1',
                'issue_date' => '2026-03-26',
                'issue_time' => '11:00:00-05:00',
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
                'name' => 'Cliente NC',
            ],
            'lines' => [
                [
                    'sequence' => '1',
                    'description' => 'Devolucion hospedaje',
                    'quantity' => '1',
                    'unit_price' => '100000.00',
                    'line_total' => '100000.00',
                ],
            ],
            'totals' => [
                'line_extension_amount' => '100000.00',
                'tax_exclusive_amount' => '100000.00',
                'tax_inclusive_amount' => '119000.00',
                'payable_amount' => '119000.00',
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
        $xp->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');
        return $xp;
    }
}
