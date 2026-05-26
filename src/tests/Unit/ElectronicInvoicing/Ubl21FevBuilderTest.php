<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use App\Infrastructure\ElectronicInvoicing\Ubl\Ubl21FevBuilder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class Ubl21FevBuilderTest extends TestCase
{
    /** @var Ubl21FevBuilder */
    private $builder;

    /** @var string */
    private $cufe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new Ubl21FevBuilder();
        $this->cufe = str_repeat('a', 96);
    }

    public function test_metadata_reports_fev_and_anexo_version(): void
    {
        $this->assertSame(DocumentType::FEV, $this->builder->documentType());
        $this->assertSame('1.9', $this->builder->anexoVersion());
    }

    public function test_build_produces_well_formed_invoice_xml(): void
    {
        $xml = $this->builder->build($this->validPayload());

        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $this->assertSame('Invoice', $doc->documentElement->localName);
        $this->assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            $doc->documentElement->namespaceURI
        );
    }

    public function test_xml_declares_minimum_ubl_and_dian_namespaces(): void
    {
        $xml = $this->builder->build($this->validPayload());
        foreach (
            [
                'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
                'dian:gov:co:facturaelectronica:Structures-2-1',
            ] as $ns
        ) {
            $this->assertStringContainsString($ns, $xml, "Expected namespace {$ns} in XML.");
        }
    }

    public function test_invoice_type_code_is_01_for_fev(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $nodes = $xp->query('//cbc:InvoiceTypeCode');
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length);
        $this->assertSame('01', $nodes->item(0)->textContent);
    }

    public function test_uuid_carries_cufe_and_scheme_attributes(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $uuid = $xp->query('//cbc:UUID')->item(0);
        $this->assertNotNull($uuid);
        $this->assertSame($this->cufe, $uuid->textContent);
        $this->assertSame('2', $uuid->getAttribute('schemeID'));
        $this->assertSame('CUFE-SHA384', $uuid->getAttribute('schemeName'));
    }

    public function test_line_count_numeric_matches_payload(): void
    {
        $payload = $this->validPayload();
        $payload['lines'][] = $this->extraLine();
        $xp = $this->xpath($this->builder->build($payload));
        $lineCount = $xp->query('//cbc:LineCountNumeric')->item(0);
        $this->assertSame('2', $lineCount->textContent);
        $this->assertSame(2, $xp->query('//cac:InvoiceLine')->length);
    }

    public function test_legal_monetary_total_and_tax_total_present(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $this->assertSame(1, $xp->query('//cac:LegalMonetaryTotal')->length);
        $payable = $xp->query('//cac:LegalMonetaryTotal/cbc:PayableAmount')->item(0);
        $this->assertSame('COP', $payable->getAttribute('currencyID'));
        $this->assertSame('119000.00', $payable->textContent);
        $this->assertGreaterThanOrEqual(1, $xp->query('//cac:TaxTotal')->length);
    }

    public function test_dian_extensions_emitted_when_payload_provides_them(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $this->assertSame(
            'SSC-VALUE-' . str_repeat('a', 96),
            $xp->query('//sts:DianExtensions/sts:SoftwareSecurityCode')->item(0)->textContent
        );
        $this->assertSame(
            'SOFT-ID-PLACEHOLDER',
            $xp->query('//sts:DianExtensions/sts:SoftwareProvider/sts:SoftwareID')->item(0)->textContent
        );
    }

    public function test_dian_extensions_skip_optional_nodes_when_absent(): void
    {
        $payload = $this->validPayload();
        unset($payload['dian_extensions']);
        $xp = $this->xpath($this->builder->build($payload));
        $this->assertSame(0, $xp->query('//sts:SoftwareSecurityCode')->length);
        $this->assertSame(0, $xp->query('//sts:SoftwareProvider')->length);
    }

    public function test_payload_missing_required_field_throws(): void
    {
        $payload = $this->validPayload();
        unset($payload['supplier']['nit']);

        $this->expectException(IncompleteUblPayloadException::class);
        $this->expectExceptionMessage('supplier.nit');
        $this->builder->build($payload);
    }

    public function test_payload_missing_lines_throws(): void
    {
        $payload = $this->validPayload();
        $payload['lines'] = [];

        $this->expectException(IncompleteUblPayloadException::class);
        $this->expectExceptionMessage('lines');
        $this->builder->build($payload);
    }

    public function test_secrets_never_leak_into_xml(): void
    {
        $payload = $this->validPayload();
        $xml = $this->builder->build($payload);

        $this->assertStringNotContainsString('SHOULD_NEVER_APPEAR_PIN', $xml);
        $this->assertStringNotContainsString('CERT_PASSWORD_LITERAL', $xml);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $xml);
    }

    public function test_signature_extension_slot_is_present_but_empty(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $extensions = $xp->query('//ext:UBLExtensions/ext:UBLExtension');
        $this->assertSame(2, $extensions->length, 'FEV envelope must reserve 2 UBL extensions (DIAN + signature).');
        $signatureContent = $xp->query('//ext:UBLExtensions/ext:UBLExtension[2]/ext:ExtensionContent')->item(0);
        $this->assertNotNull($signatureContent);
        $this->assertSame(0, $signatureContent->childNodes->length, 'Signature slot must be empty until XAdES slice wires it.');
    }

    private function validPayload(): array
    {
        return [
            'document' => [
                'type' => DocumentType::FEV,
                'prefix' => 'SETP',
                'sequence' => 990000001,
                'number' => 'SETP990000001',
                'issue_date' => '2026-03-26',
                'issue_time' => '10:30:45-05:00',
                'currency' => 'COP',
                'environment' => '2',
                'cufe' => $this->cufe,
            ],
            'supplier' => [
                'nit' => '900000000',
                'verification_digit' => '7',
                'name' => 'CAMPO VERDE S.A.S.',
                'address_line' => 'Cra 1 #1-1',
                'city_code' => '11001',
                'city_name' => 'Bogota',
                'department_name' => 'Bogota D.C.',
                'country_code' => 'CO',
                'id_type' => '31',
            ],
            'customer' => [
                'id' => '1010101010',
                'id_type' => '13',
                'name' => 'Cliente de Prueba',
                'country_code' => 'CO',
            ],
            'lines' => [
                [
                    'sequence' => '1',
                    'description' => 'Hospedaje noche',
                    'quantity' => '1.00',
                    'unit_code' => 'NIU',
                    'unit_price' => '100000.00',
                    'line_total' => '100000.00',
                    'tax_amount' => '19000.00',
                    'taxable_amount' => '100000.00',
                    'tax_percent' => '19.00',
                ],
            ],
            'totals' => [
                'line_extension_amount' => '100000.00',
                'tax_exclusive_amount' => '100000.00',
                'tax_inclusive_amount' => '119000.00',
                'payable_amount' => '119000.00',
            ],
            'taxes' => [
                [
                    'code' => '01',
                    'name' => 'IVA',
                    'percent' => '19.00',
                    'taxable_amount' => '100000.00',
                    'tax_amount' => '19000.00',
                ],
            ],
            'payment' => [
                'means_code' => '10',
                'terms_code' => '1',
            ],
            'dian_extensions' => [
                'invoice_authorization' => '18760000001',
                'authorization_period_start' => '2026-01-01',
                'authorization_period_end' => '2027-01-01',
                'authorized_prefix' => 'SETP',
                'authorized_from' => '990000000',
                'authorized_to' => '995000000',
                'software_id' => 'SOFT-ID-PLACEHOLDER',
                'software_security_code' => 'SSC-VALUE-' . str_repeat('a', 96),
                'qr_url' => 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=' . $this->cufe,
            ],
        ];
    }

    private function extraLine(): array
    {
        return [
            'sequence' => '2',
            'description' => 'Tour guiado',
            'quantity' => '1.00',
            'unit_code' => 'ACT',
            'unit_price' => '50000.00',
            'line_total' => '50000.00',
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
