<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21DeePosBuilder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class Ubl21DeePosBuilderTest extends TestCase
{
    /** @var Ubl21DeePosBuilder */
    private $builder;

    /** @var string */
    private $cude;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new Ubl21DeePosBuilder();
        $this->cude = str_repeat('b', 96);
    }

    public function test_metadata_reports_dee_pos(): void
    {
        $this->assertSame(DocumentType::DEE_POS, $this->builder->documentType());
        $this->assertSame('DEE-1.0', $this->builder->anexoVersion());
    }

    public function test_root_is_invoice_for_dee_pos_envelope(): void
    {
        $xml = $this->builder->build($this->payloadWithoutCustomer());
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $this->assertSame('Invoice', $doc->documentElement->localName);
    }

    public function test_invoice_type_code_is_20_for_dee_pos(): void
    {
        $xp = $this->xpath($this->builder->build($this->payloadWithoutCustomer()));
        $nodes = $xp->query('//cbc:InvoiceTypeCode');
        $this->assertSame(1, $nodes->length);
        $this->assertSame('20', $nodes->item(0)->textContent);
    }

    public function test_customer_party_is_optional(): void
    {
        $xp = $this->xpath($this->builder->build($this->payloadWithoutCustomer()));
        $this->assertSame(0, $xp->query('//cac:AccountingCustomerParty')->length);
        $this->assertSame(1, $xp->query('//cac:AccountingSupplierParty')->length);
    }

    public function test_customer_party_is_emitted_when_provided(): void
    {
        $payload = $this->payloadWithoutCustomer();
        $payload['customer'] = [
            'id' => '999999999',
            'id_type' => '13',
            'name' => 'Adquiriente POS',
        ];
        $xp = $this->xpath($this->builder->build($payload));
        $this->assertSame(1, $xp->query('//cac:AccountingCustomerParty')->length);
    }

    public function test_uuid_carries_cude(): void
    {
        $xp = $this->xpath($this->builder->build($this->payloadWithoutCustomer()));
        $this->assertSame($this->cude, $xp->query('//cbc:UUID')->item(0)->textContent);
    }

    private function payloadWithoutCustomer(): array
    {
        return [
            'document' => [
                'type' => DocumentType::DEE_POS,
                'prefix' => 'POS',
                'sequence' => 1,
                'number' => 'POS1',
                'issue_date' => '2026-03-26',
                'issue_time' => '12:00:00-05:00',
                'currency' => 'COP',
                'environment' => '2',
                'cufe' => $this->cude,
            ],
            'supplier' => [
                'nit' => '900000000',
                'name' => 'CAMPO VERDE S.A.S.',
                'id_type' => '31',
            ],
            'lines' => [
                [
                    'sequence' => '1',
                    'description' => 'Snack',
                    'quantity' => '1',
                    'unit_price' => '5000.00',
                    'line_total' => '5000.00',
                ],
            ],
            'totals' => [
                'line_extension_amount' => '5000.00',
                'tax_exclusive_amount' => '5000.00',
                'tax_inclusive_amount' => '5000.00',
                'payable_amount' => '5000.00',
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
